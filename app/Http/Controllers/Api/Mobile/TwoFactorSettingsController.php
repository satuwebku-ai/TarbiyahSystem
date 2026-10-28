<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use App\Services\TwoFactorPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pengaturan 2FA (TOTP) pegawai dari menu Profil > Keamanan akun di aplikasi mobile.
 *
 * Berbeda dengan TwoFactorEnrollmentController (jalur dari layar login untuk akun WAJIB 2FA yang belum punya token),
 * endpoint ini memakai token Bearer sehingga pegawai yang sudah masuk bisa mengaktifkan, membuat ulang kode pemulihan,
 * atau mematikan 2FA dari pengaturan. Aturannya sama dengan portal web (BaseTwoFactorController).
 *
 *  - GET  /security/two-factor                 -> status
 *  - POST /security/two-factor/setup           -> {secret, otpauthUri, issuer, account}
 *  - POST /security/two-factor/confirm {code}  -> {recoveryCodes}
 *  - POST /security/two-factor/recovery-codes {password} -> {recoveryCodes}
 *  - POST /security/two-factor/disable {password}        -> status
 */
class TwoFactorSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->status($request->user()));
    }

    public function setup(Request $request, TwoFactorAuthenticator $totp): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($response = $this->guardFeature()) {
            return $response;
        }
        if ($user->hasTwoFactorEnabled()) {
            return $this->error('two_factor_already_enabled', '2FA sudah aktif untuk akun ini.', 422);
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
        $account = (string) ($user->username ?? $user->email);

        return response()->json([
            'secret' => $secret,
            'otpauthUri' => $totp->provisioningUri($issuer, $account, $secret),
            'issuer' => $issuer,
            'account' => $account,
        ]);
    }

    public function confirm(Request $request, TwoFactorAuthenticator $totp): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        if ($response = $this->guardFeature()) {
            return $response;
        }
        if (! $user->hasPendingTwoFactorSetup()) {
            return $this->error('two_factor_setup_missing', 'Mulai pendaftaran 2FA lebih dulu.', 422);
        }

        $settings = AdminSecuritySetting::current();
        $key = '2fa|pegawai|'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, $settings->max_login_attempts)) {
            return $this->error('too_many_attempts', 'Terlalu banyak kode salah. Coba lagi beberapa saat lagi.', 429);
        }

        $step = $totp->verify((string) $user->two_factor_secret, $data['code']);
        if ($step === null) {
            RateLimiter::hit($key, max(60, $settings->lockout_minutes * 60));
            AdminAuditLog::record('pegawai.two_factor_failed', null, $user, ['channel' => 'mobile', 'during' => 'settings']);

            return $this->error('two_factor_invalid', 'Kode salah. Pastikan jam ponsel akurat, lalu masukkan kode 6 digit terbaru.', 422);
        }
        RateLimiter::clear($key);

        $user->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $codes = $user->regenerateRecoveryCodes();
        AdminAuditLog::record('pegawai.two_factor_enabled', $user, $user, ['channel' => 'mobile']);

        return response()->json(['recoveryCodes' => $codes] + $this->status($user));
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($response = $this->checkPassword($request, $user)) {
            return $response;
        }
        if (! $user->hasTwoFactorEnabled()) {
            return $this->error('two_factor_not_enabled', '2FA belum aktif.', 422);
        }

        $codes = $user->regenerateRecoveryCodes();
        AdminAuditLog::record('pegawai.two_factor_recovery_regenerated', $user, $user, ['channel' => 'mobile']);

        return response()->json(['recoveryCodes' => $codes] + $this->status($user));
    }

    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($response = $this->checkPassword($request, $user)) {
            return $response;
        }
        if (TwoFactorPolicy::isRequiredFor($user) && $user->hasTwoFactorEnabled()) {
            return $this->error('two_factor_required', 'Kebijakan keamanan mewajibkan 2FA untuk akun Anda, sehingga tidak bisa dimatikan.', 403);
        }

        $wasEnabled = $user->hasTwoFactorEnabled();
        $user->clearTwoFactor();
        if ($wasEnabled) {
            AdminAuditLog::record('pegawai.two_factor_disabled', $user, $user, ['by' => 'self', 'channel' => 'mobile']);
        }

        return response()->json($this->status($user->fresh()));
    }

    private function status(User $user): array
    {
        return [
            'featureEnabled' => TwoFactorPolicy::featureEnabled(),
            'enabled' => $user->hasTwoFactorEnabled(),
            'pending' => $user->hasPendingTwoFactorSetup(),
            'required' => TwoFactorPolicy::isRequiredFor($user),
            'recoveryCodesRemaining' => $user->recoveryCodesRemaining(),
        ];
    }

    private function guardFeature(): ?JsonResponse
    {
        if (! TwoFactorPolicy::featureEnabled()) {
            return $this->error('two_factor_disabled', 'Autentikasi dua faktor sedang dinonaktifkan oleh pengelola.', 403);
        }

        return null;
    }

    /** Kata sandi wajib untuk tindakan sensitif; salah berkali-kali dikunci seperti login (per akun dan per IP). */
    private function checkPassword(Request $request, User $user): ?JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:255']]);

        $settings = AdminSecuritySetting::current();
        $key = 'security-password|pegawai|'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, $settings->max_login_attempts ?? 5)) {
            $seconds = RateLimiter::availableIn($key);

            return $this->error('too_many_attempts', "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.", 429);
        }

        if (! Hash::check($data['password'], (string) $user->password)) {
            RateLimiter::hit($key, ($settings->lockout_minutes ?? 1) * 60);
            AdminAuditLog::record('pegawai.security_password_failed', null, $user, ['channel' => 'mobile']);

            return $this->error('invalid_password', 'Kata sandi salah.', 422);
        }
        RateLimiter::clear($key);

        return null;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status);
    }
}
