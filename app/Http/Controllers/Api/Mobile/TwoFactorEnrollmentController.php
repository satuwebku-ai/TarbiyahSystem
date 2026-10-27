<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\MobileToken;
use App\Models\User;
use App\Services\PegawaiPortalAccess;
use App\Services\TwoFactorAuthenticator;
use App\Services\TwoFactorPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Pendaftaran 2FA (TOTP) pegawai dari aplikasi mobile.
 *
 * Akun yang WAJIB 2FA tetapi belum mendaftar tidak bisa mendapat token (login menjawab
 * `two_factor_enrollment_required`), jadi kedua endpoint ini dibuka dengan username + kata sandi, bukan token.
 * Aturannya sama dengan pendaftaran di portal web: rahasia baru tiap kali mulai, wajib konfirmasi kode 6 digit,
 * lalu kode pemulihan ditampilkan sekali. Batas percobaan memakai kunci yang sama dengan login (web dan mobile).
 *
 *  - POST /two-factor/setup   {identifier, password}                   -> {secret, otpauthUri, issuer, account}
 *  - POST /two-factor/confirm {identifier, password, code, deviceName?} -> {token, expiresAt, user, recoveryCodes}
 *
 * Hanya untuk pegawai; pelajar dan pengelola tidak punya 2FA di API ini.
 */
class TwoFactorEnrollmentController extends Controller
{
    public function setup(Request $request, PegawaiPortalAccess $access, TwoFactorAuthenticator $totp): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->authenticate($request, $data['identifier'], $data['password'], $access, $failure);
        if (! $user) {
            return $failure;
        }
        if ($response = $this->guardEnrollable($user)) {
            return $response;
        }

        // Rahasia baru tiap kali (pendaftaran yang belum dikonfirmasi diulang dari awal), sama seperti di web.
        $secret = $totp->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        $issuer = (string) config('app.name');

        return response()->json([
            'secret' => $secret,
            'otpauthUri' => $totp->provisioningUri($issuer, (string) ($user->username ?? $user->email), $secret),
            'issuer' => $issuer,
            'account' => (string) ($user->username ?? $user->email),
        ]);
    }

    public function confirm(Request $request, PegawaiPortalAccess $access, TwoFactorAuthenticator $totp): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->authenticate($request, $data['identifier'], $data['password'], $access, $failure);
        if (! $user) {
            return $failure;
        }
        if ($response = $this->guardEnrollable($user)) {
            return $response;
        }
        if (! $user->hasPendingTwoFactorSetup()) {
            return response()->json([
                'code' => 'two_factor_setup_missing',
                'message' => 'Mulai pendaftaran 2FA lebih dulu.',
            ], 422);
        }

        // Tebakan kode dibatasi dengan kunci yang sama dengan tantangan 2FA saat login.
        $settings = AdminSecuritySetting::current();
        $key = '2fa|pegawai|'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, $settings->max_login_attempts)) {
            return response()->json([
                'code' => 'too_many_attempts',
                'message' => 'Terlalu banyak kode salah. Coba lagi beberapa saat lagi.',
            ], 429);
        }

        $step = $totp->verify((string) $user->two_factor_secret, $data['code']);
        if ($step === null) {
            RateLimiter::hit($key, max(60, $settings->lockout_minutes * 60));
            AdminAuditLog::record('pegawai.two_factor_failed', null, $user, ['channel' => 'mobile', 'during' => 'enrollment']);

            return response()->json([
                'code' => 'two_factor_invalid',
                'message' => 'Kode salah. Pastikan jam ponsel akurat, lalu masukkan kode 6 digit terbaru.',
            ], 422);
        }
        RateLimiter::clear($key);

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
        $recoveryCodes = $user->regenerateRecoveryCodes();

        AdminAuditLog::record('pegawai.two_factor_enabled', $user, $user, ['channel' => 'mobile']);

        // Kode barusan membuktikan kepemilikan autentikator, jadi langsung masuk (tanpa meminta kode yang sama lagi).
        [$token, $plain] = MobileToken::issue($user, $data['deviceName'] ?? 'mobile');
        AdminAuditLog::record('pegawai.login_success', $user, $user, ['channel' => 'mobile', 'two_factor' => 'totp']);

        return response()->json([
            'token' => $plain,
            'expiresAt' => $token->expires_at?->toIso8601String(),
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'role' => 'pegawai',
                'identifier' => $user->username,
            ],
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Periksa username + kata sandi pegawai dengan aturan login (batas percobaan yang sama, akun nonaktif,
     * syarat portal pegawai). Mengembalikan User, atau null dengan $failure berisi jawaban galat.
     */
    private function authenticate(Request $request, string $identifier, string $password, PegawaiPortalAccess $access, ?JsonResponse &$failure): ?User
    {
        $identifier = trim($identifier);
        $failure = null;
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $identifier)) {
            $failure = $this->invalid();

            return null;
        }

        $settings = AdminSecuritySetting::current();
        $maxAttempts = $settings->max_login_attempts ?? 5;
        $decaySeconds = ($settings->lockout_minutes ?? 1) * 60;

        $throttleKey = Str::lower($identifier).'|'.$request->ip().'|pegawai';
        $ipKey = 'login-ip|pegawai|'.$request->ip();
        $ipMax = max(1, (int) config('admin_security.login_ip_max_failures', 30));
        $ipDecay = max(60, (int) config('admin_security.login_ip_decay_seconds', 600));

        foreach ([[$ipKey, $ipMax], [$throttleKey, $maxAttempts]] as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $seconds = RateLimiter::availableIn($key);
                AdminAuditLog::record('pegawai.login_throttled', null, null, [
                    'identifier' => mb_substr($identifier, 0, 50), 'channel' => 'mobile', 'during' => 'enrollment',
                ]);
                $failure = response()->json([
                    'code' => 'too_many_attempts',
                    'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                ], 429);

                return null;
            }
        }

        $user = User::query()->where('username', Str::lower($identifier))->where('is_active', true)->first();
        if (! $user || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, $decaySeconds);
            RateLimiter::hit($ipKey, $ipDecay);
            AdminAuditLog::record('pegawai.login_failed', null, null, [
                'identifier' => mb_substr($identifier, 0, 50), 'channel' => 'mobile', 'reason' => 'invalid_credentials', 'during' => 'enrollment',
            ]);
            $failure = $this->invalid();

            return null;
        }

        if ($reason = $access->denialReason($user)) {
            RateLimiter::hit($throttleKey, $decaySeconds);
            RateLimiter::hit($ipKey, $ipDecay);
            AdminAuditLog::record('pegawai.login_failed', null, null, [
                'identifier' => mb_substr($identifier, 0, 50), 'channel' => 'mobile', 'reason' => 'denied', 'during' => 'enrollment',
            ]);
            $failure = response()->json(['code' => 'account_denied', 'message' => $reason], 403);

            return null;
        }

        // Kata sandi benar: kunci per akun dilepas, tetapi kunci per IP tetap menghitung agar tebakan massal tidak lolos.
        RateLimiter::clear($throttleKey);

        return $user;
    }

    /** Tolak bila fitur 2FA dimatikan pengelola atau 2FA sudah aktif (pendaftaran ulang harus lewat portal). */
    private function guardEnrollable(User $user): ?JsonResponse
    {
        if (! TwoFactorPolicy::featureEnabled()) {
            return response()->json([
                'code' => 'two_factor_disabled',
                'message' => 'Autentikasi dua faktor sedang dinonaktifkan oleh pengelola.',
            ], 403);
        }
        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'code' => 'two_factor_already_enabled',
                'message' => '2FA sudah aktif untuk akun ini. Masuk dengan kode dari aplikasi autentikator.',
            ], 422);
        }

        return null;
    }

    private function invalid(): JsonResponse
    {
        return response()->json([
            'code' => 'invalid_credentials',
            'message' => 'Username atau kata sandi salah, atau akun dinonaktifkan.',
        ], 422);
    }
}
