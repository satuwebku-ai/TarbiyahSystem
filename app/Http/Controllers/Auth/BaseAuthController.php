<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
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
        $request->session()->regenerate();

        $guard->user()->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
        if ($this->usesStaffPolicy()) {
            AdminAuditLog::record($this->guardName().'.login_success', $guard->user(), $guard->user());
        }

        return redirect()->intended(route($this->homeRoute()));
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
