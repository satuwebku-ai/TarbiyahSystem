<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\MobileToken;
use App\Models\StudentAccount;
use App\Models\User;
use App\Services\PegawaiPortalAccess;
use App\Services\TwoFactorAuthenticator;
use App\Services\TwoFactorPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Login aplikasi mobile. Aturannya sama dengan portal web (kunci batas percobaan yang sama, kebijakan
 * keamanan, 2FA, audit log) — bedanya hasilnya token Bearer, bukan sesi cookie, dan tanpa CAPTCHA
 * (CAPTCHA butuh halaman web; batas percobaan per akun dan per IP tetap berlaku).
 */
class AuthController extends Controller
{
    private const MESSAGES = [
        'pegawai' => 'Username atau kata sandi salah, atau akun dinonaktifkan.',
        'pelajar' => 'NIS atau kata sandi salah, atau akun dinonaktifkan.',
    ];

    public function login(Request $request, PegawaiPortalAccess $access, TwoFactorAuthenticator $totp): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['pegawai', 'pelajar'])],
            'identifier' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
            'twoFactorCode' => ['nullable', 'string', 'max:64'],
            'deviceName' => ['nullable', 'string', 'max:100'],
        ]);

        $role = $data['role'];
        $identifier = trim($data['identifier']);
        if ($role === 'pegawai' && ! preg_match('/^[A-Za-z0-9._-]+$/', $identifier)) {
            return $this->invalid($role);
        }

        $staff = $role === 'pegawai';
        $settings = $staff ? AdminSecuritySetting::current() : null;
        $maxAttempts = $settings?->max_login_attempts ?? 5;
        $decaySeconds = ($settings?->lockout_minutes ?? 1) * 60;

        // Kunci yang sama dengan login web, sehingga kuncian di web dan di mobile saling berlaku.
        $throttleKey = Str::lower($identifier).'|'.$request->ip().'|'.$role;
        $ipKey = 'login-ip|'.$role.'|'.$request->ip();
        $ipMax = max(1, (int) config('admin_security.login_ip_max_failures', 30));
        $ipDecay = max(60, (int) config('admin_security.login_ip_decay_seconds', 600));

        foreach ([[$ipKey, $ipMax, 'ip'], [$throttleKey, $maxAttempts, 'account']] as [$key, $limit, $scope]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $seconds = RateLimiter::availableIn($key);
                $this->audit($role, 'login_throttled', $identifier, ['scope' => $scope]);

                return response()->json([
                    'code' => 'too_many_attempts',
                    'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                ], 429);
            }
        }

        $account = $staff
            ? User::query()->where('username', Str::lower($identifier))->where('is_active', true)->first()
            : StudentAccount::query()->where('nis', $identifier)->where('is_active', true)->first();

        if (! $account || ! Hash::check($data['password'], (string) $account->password)) {
            RateLimiter::hit($throttleKey, $decaySeconds);
            RateLimiter::hit($ipKey, $ipDecay);
            $this->audit($role, 'login_failed', $identifier, ['reason' => 'invalid_credentials']);

            return $this->invalid($role);
        }

        if ($account instanceof User && ($reason = $access->denialReason($account))) {
            RateLimiter::hit($throttleKey, $decaySeconds);
            RateLimiter::hit($ipKey, $ipDecay);
            $this->audit($role, 'login_failed', $identifier, ['reason' => 'denied']);

            return response()->json(['code' => 'account_denied', 'message' => $reason], 403);
        }

        $twoFactorMethod = null;
        if ($account instanceof User) {
            if (TwoFactorPolicy::mustEnroll($account)) {
                return response()->json([
                    'code' => 'two_factor_enrollment_required',
                    'message' => 'Akun ini wajib mengaktifkan 2FA melalui portal Pegawai sebelum masuk.',
                ], 403);
            }

            if (TwoFactorPolicy::challengeRequiredFor($account)) {
                $code = trim((string) ($data['twoFactorCode'] ?? ''));
                if ($code === '') {
                    return response()->json([
                        'code' => 'two_factor_required',
                        'message' => 'Masukkan kode 2FA dari aplikasi autentikator.',
                    ], 422);
                }

                $twoFactorMethod = $this->verifyTwoFactor($account, $code, $totp, $settings);
                if ($twoFactorMethod === 'throttled') {
                    return response()->json([
                        'code' => 'too_many_attempts',
                        'message' => 'Terlalu banyak kode salah. Coba lagi beberapa saat lagi.',
                    ], 429);
                }
                if ($twoFactorMethod === null) {
                    return response()->json([
                        'code' => 'two_factor_invalid',
                        'message' => 'Kode tidak valid atau sudah dipakai. Periksa jam di ponsel Anda, atau gunakan kode pemulihan.',
                    ], 422);
                }
            }
        }

        RateLimiter::clear($throttleKey);

        $account->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        [$token, $plain] = MobileToken::issue($account, $data['deviceName'] ?? 'mobile');

        if ($staff) {
            AdminAuditLog::record('pegawai.login_success', $account, $account, array_filter([
                'channel' => 'mobile',
                'two_factor' => $twoFactorMethod,
            ]));
        }

        return response()->json([
            'token' => $plain,
            'expiresAt' => $token->expires_at?->toIso8601String(),
            'user' => $this->userPayload($account),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('mobile_token')?->delete();

        return response()->json(['message' => 'Sesi mobile dicabut.']);
    }

    /** 'totp' | 'recovery_code' bila benar, 'throttled' bila dibatasi, null bila salah. */
    private function verifyTwoFactor(User $user, string $code, TwoFactorAuthenticator $totp, AdminSecuritySetting $settings): ?string
    {
        $key = '2fa|pegawai|'.$user->getAuthIdentifier();
        if (RateLimiter::tooManyAttempts($key, $settings->max_login_attempts)) {
            return 'throttled';
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
            AdminAuditLog::record('pegawai.two_factor_failed', null, $user, ['channel' => 'mobile']);

            return null;
        }

        RateLimiter::clear($key);

        return $method;
    }

    private function invalid(string $role): JsonResponse
    {
        return response()->json(['code' => 'invalid_credentials', 'message' => self::MESSAGES[$role]], 422);
    }

    /** Audit login hanya untuk akun staf (sama seperti portal web); pelajar tidak dicatat. */
    private function audit(string $role, string $event, string $identifier, array $details = []): void
    {
        if ($role !== 'pegawai') {
            return;
        }

        AdminAuditLog::record('pegawai.'.$event, null, null, [
            'identifier' => mb_substr($identifier, 0, 50),
            'channel' => 'mobile',
        ] + $details);
    }

    private function userPayload(User|StudentAccount $account): array
    {
        $role = MobileToken::roleOf($account);

        return [
            'id' => $account->getKey(),
            'name' => $account->name,
            'role' => $role,
            'identifier' => $role === 'pegawai' ? $account->username : $account->nis,
        ];
    }
}
