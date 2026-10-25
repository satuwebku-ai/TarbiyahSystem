<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Services\Captcha;
use App\Services\TwoFactorAuthenticator;
use App\Services\TwoFactorPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Logika login/logout bersama. Subclass hanya menentukan guard, view & route.
 */
abstract class BaseAuthController extends Controller
{
    abstract protected function guardName(): string;   // admin | pegawai | pelajar
    abstract protected function loginView(): string;
    abstract protected function homeRoute(): string;
    abstract protected function loginRoute(): string;

    /** Menit tantangan 2FA berlaku sejak kata sandi benar; lewat dari itu harus login ulang. */
    private const TWO_FACTOR_TTL_MINUTES = 5;

    /** Nama input form untuk identitas login. Default: email. */
    protected function loginField(): string { return 'email'; }

    protected function loginRules(): array { return ['required', 'email']; }

    /**
     * Pemeriksaan tambahan setelah kata sandi benar (mis. akun harus terhubung ke data pegawai).
     * Kembalikan pesan penolakan, atau null bila boleh masuk.
     */
    protected function denyLogin($user): ?string { return null; }

    /** Guard staf (admin & pegawai) memakai kebijakan keamanan dan audit log yang sama; pelajar tidak. */
    private function usesStaffPolicy(): bool
    {
        return in_array($this->guardName(), ['admin', 'pegawai'], true);
    }

    /** Ubah input identitas menjadi kolom kredensial untuk guard->attempt(). */
    protected function credentialColumn(string $identifier): array
    {
        return ['email' => $identifier];
    }

    public function showLogin(): View
    {
        return view($this->loginView());
    }

    public function login(Request $request): RedirectResponse
    {
        $field = $this->loginField();
        $input = $request->validate([
            $field     => $this->loginRules(),
            'password' => ['required', 'string'],
        ]);
        // CAPTCHA diperiksa sebelum hitungan percobaan: bot tanpa CAPTCHA tidak bisa mengunci akun orang lain.
        app(Captcha::class)->verify($request, 'login');
        $credentials = [...$this->credentialColumn(trim($input[$field])), 'password' => $input['password']];

        $throttleKey = Str::lower(trim($input[$field])) . '|' . $request->ip() . '|' . $this->guardName();
        $settings = $this->usesStaffPolicy() ? AdminSecuritySetting::current() : null;
        $maxAttempts = $settings?->max_login_attempts ?? 5;
        $decaySeconds = ($settings?->lockout_minutes ?? 1) * 60;

        // Batas kedua per IP (semua username): menahan password spraying yang mengganti-ganti username.
        $ipKey = 'login-ip|'.$this->guardName().'|'.$request->ip();
        $ipMax = max(1, (int) config('admin_security.login_ip_max_failures', 30));
        $ipDecay = max(60, (int) config('admin_security.login_ip_decay_seconds', 600));

        if (RateLimiter::tooManyAttempts($ipKey, $ipMax)) {
            $seconds = RateLimiter::availableIn($ipKey);
            $this->auditLogin('login_throttled', $input[$field], ['scope' => 'ip'], $ipKey, $seconds);

            throw ValidationException::withMessages([
                $field => "Terlalu banyak percobaan login dari jaringan ini. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->auditLogin('login_throttled', $input[$field], ['scope' => 'account'], $throttleKey, $seconds);

            throw ValidationException::withMessages([
                $field => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $guard = Auth::guard($this->guardName());

        // Akun dengan 2FA aktif: kata sandi diperiksa tanpa login dulu; sesi baru dibuat setelah kode 2FA benar.
        // (Jangan memakai attempt() di sini: ia langsung login dan bisa mengirim cookie "ingat saya".)
        if ($candidate = $this->twoFactorCandidate($guard, [...$credentials, 'is_active' => true])) {
            if ($message = $this->denyLogin($candidate)) {
                RateLimiter::hit($throttleKey, $decaySeconds);
                RateLimiter::hit($ipKey, $ipDecay);
                $this->auditLogin('login_failed', $input[$field], ['reason' => 'denied']);

                throw ValidationException::withMessages([$field => $message]);
            }

            RateLimiter::clear($throttleKey);

            return $this->startTwoFactorChallenge($request, $candidate);
        }

        // is_active = true -> akun nonaktif tidak bisa masuk.
        if (! $guard->attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, $decaySeconds);
            RateLimiter::hit($ipKey, $ipDecay);
            $this->auditLogin('login_failed', $input[$field], ['reason' => 'invalid_credentials']);

            throw ValidationException::withMessages([
                $field => $this->failedMessage(),
            ]);
        }

        if ($message = $this->denyLogin($guard->user())) {
            $guard->logout();
            RateLimiter::hit($throttleKey, $decaySeconds);
            RateLimiter::hit($ipKey, $ipDecay);
            $this->auditLogin('login_failed', $input[$field], ['reason' => 'denied']);

            throw ValidationException::withMessages([$field => $message]);
        }

        RateLimiter::clear($throttleKey);

        return $this->finishLogin($request, $guard->user());
    }

    /** Langkah akhir login berhasil (juga dipakai setelah tantangan 2FA): sesi baru, catat login terakhir, audit, redirect. */
    private function finishLogin(Request $request, $user, array $auditDetails = []): RedirectResponse
    {
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
        if ($this->usesStaffPolicy()) {
            AdminAuditLog::record($this->guardName().'.login_success', $user, $user, $auditDetails);
        }

        return redirect()->intended(route($this->homeRoute()));
    }

    // ---- 2FA (TOTP) saat login ---------------------------------------------------------------

    private function pendingKey(): string
    {
        return 'two_factor.pending.'.$this->guardName();
    }

    /** Akun yang kata sandinya benar dan wajib melewati tantangan 2FA; null bila tidak berlaku. */
    private function twoFactorCandidate($guard, array $lookup)
    {
        if (! $this->usesStaffPolicy() || ! TwoFactorPolicy::featureEnabled()) {
            return null;
        }

        $provider = $guard->getProvider();
        $user = $provider->retrieveByCredentials($lookup);

        return $user && TwoFactorPolicy::challengeRequiredFor($user) && $provider->validateCredentials($user, $lookup)
            ? $user
            : null;
    }

    private function startTwoFactorChallenge(Request $request, $user): RedirectResponse
    {
        $request->session()->put($this->pendingKey(), [
            'id' => $user->getAuthIdentifier(),
            'remember' => $request->boolean('remember'),
            'expires' => now()->addMinutes(self::TWO_FACTOR_TTL_MINUTES)->timestamp,
        ]);
        $request->session()->regenerate();

        return redirect()->route($this->twoFactorRoute());
    }

    /** Akun yang sedang menunggu kode 2FA, atau null (tidak ada / kedaluwarsa / akun berubah). */
    private function pendingTwoFactorUser(Request $request)
    {
        $pending = $request->session()->get($this->pendingKey());
        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget($this->pendingKey());

            return null;
        }

        $user = Auth::guard($this->guardName())->getProvider()->retrieveById($pending['id']);
        if (! $user || ! $user->is_active || ! TwoFactorPolicy::challengeRequiredFor($user) || $this->denyLogin($user)) {
            $request->session()->forget($this->pendingKey());

            return null;
        }

        return $user;
    }

    public function showTwoFactor(Request $request): View|RedirectResponse
    {
        abort_unless($this->usesStaffPolicy(), 404);

        if (! $this->pendingTwoFactorUser($request)) {
            return redirect()->route($this->loginRoute())->with('error', 'Sesi verifikasi berakhir. Silakan masuk kembali.');
        }

        return view('auth.two-factor-challenge', [
            'action' => route($this->twoFactorRoute().'.store'),
            'loginUrl' => route($this->loginRoute()),
            'subtitle' => $this->guardName() === 'admin' ? 'Panel Pengelola' : 'Portal Pegawai',
        ]);
    }

    public function twoFactor(Request $request, TwoFactorAuthenticator $totp): RedirectResponse
    {
        abort_unless($this->usesStaffPolicy(), 404);

        $user = $this->pendingTwoFactorUser($request);
        if (! $user) {
            return redirect()->route($this->loginRoute())->with('error', 'Sesi verifikasi berakhir. Silakan masuk kembali.');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $code = trim($data['code']);

        // Batas percobaan kode per akun (nilai yang sama dengan batas login), agar 6 digit tidak bisa ditebak.
        $settings = AdminSecuritySetting::current();
        $key = '2fa|'.$this->guardName().'|'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, $settings->max_login_attempts)) {
            $seconds = RateLimiter::availableIn($key);
            $request->session()->forget($this->pendingKey());
            $this->auditLogin('two_factor_throttled', $user->username ?? '', ['scope' => 'account'], $key, $seconds);

            return redirect()->route($this->loginRoute())->with('error', "Terlalu banyak kode salah. Coba lagi dalam {$seconds} detik.");
        }

        $method = null;
        if (preg_match('/^\d{3}\s?\d{3}$/', $code)) {
            $step = $totp->verify((string) $user->two_factor_secret, $code, $user->two_factor_last_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                $method = 'totp';
            }
        } elseif ($user->consumeRecoveryCode($code)) {
            $method = 'recovery_code';
        }

        if ($method === null) {
            RateLimiter::hit($key, max(60, $settings->lockout_minutes * 60));
            AdminAuditLog::record($this->guardName().'.two_factor_failed', null, $user, ['identifier' => mb_substr((string) ($user->username ?? ''), 0, 50)]);

            throw ValidationException::withMessages(['code' => 'Kode tidak valid atau sudah dipakai. Periksa jam di ponsel Anda, atau gunakan kode pemulihan.']);
        }

        RateLimiter::clear($key);
        $pending = $request->session()->pull($this->pendingKey());
        Auth::guard($this->guardName())->login($user, (bool) ($pending['remember'] ?? false));

        $details = ['two_factor' => $method];
        if ($method === 'recovery_code') {
            $details['recovery_codes_left'] = $user->recoveryCodesRemaining();
        }

        return $this->finishLogin($request, $user, $details);
    }

    protected function twoFactorRoute(): string
    {
        return $this->guardName().'.two-factor.challenge';
    }

    /**
     * Catat percobaan login gagal/terblokir ke audit log (guard staf saja, sama seperti login berhasil).
     * Peristiwa terblokir dicatat sekali per masa blokir agar serangan tidak membanjiri tabel.
     */
    private function auditLogin(string $event, string $identifier, array $details = [], ?string $dedupeKey = null, int $dedupeSeconds = 60): void
    {
        if (! $this->usesStaffPolicy()) {
            return;
        }
        if ($dedupeKey !== null && ! Cache::add('login-audit|'.$dedupeKey, true, max(1, $dedupeSeconds))) {
            return;
        }

        AdminAuditLog::record($this->guardName().'.'.$event, null, null, ['identifier' => mb_substr(trim($identifier), 0, 50)] + $details);
    }

    protected function failedMessage(): string
    {
        return 'Email atau kata sandi salah, atau akun dinonaktifkan.';
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::guard($this->guardName())->user();
        if ($user && $this->usesStaffPolicy()) {
            AdminAuditLog::record($this->guardName().'.logout', $user, $user);
        }
        Auth::guard($this->guardName())->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($this->loginRoute());
    }
}
