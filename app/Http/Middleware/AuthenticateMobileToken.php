<?php

namespace App\Http\Middleware;

use App\Models\MobileToken;
use App\Models\User;
use App\Services\PegawaiPortalAccess;
use App\Services\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi token Bearer untuk API mobile. Syarat akses dicek ulang di setiap permintaan
 * (akun dinonaktifkan, status pegawai berakhir, izin dicabut) sehingga pencabutan langsung berlaku.
 *
 * Pemakaian: mobile.auth (semua peran) atau mobile.auth:pegawai / mobile.auth:pelajar.
 */
class AuthenticateMobileToken
{
    public function __construct(private PegawaiPortalAccess $access) {}

    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? MobileToken::findByPlain($plain) : null;
        $account = $token?->tokenable;
        $accountRole = MobileToken::roleOf($account);

        if (! $token || ! $account || $accountRole === null || ! $account->is_active) {
            return response()->json([
                'code' => 'unauthenticated',
                'message' => 'Sesi berakhir. Silakan masuk kembali.',
            ], 401);
        }

        if ($account instanceof User) {
            if ($reason = $this->access->denialReason($account)) {
                return response()->json(['code' => 'account_denied', 'message' => $reason], 403);
            }
            if (TwoFactorPolicy::mustEnroll($account)) {
                return response()->json([
                    'code' => 'two_factor_enrollment_required',
                    'message' => 'Akun ini wajib mengaktifkan 2FA melalui portal Pegawai.',
                ], 403);
            }
        }

        if ($role !== null && $role !== $accountRole) {
            return response()->json([
                'code' => 'wrong_role',
                'message' => 'Akun ini tidak memiliki akses ke fitur tersebut.',
            ], 403);
        }

        // Cukup catat pemakaian terakhir sekali per menit agar tidak menulis di setiap permintaan.
        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()] + $this->slidingExpiry($token))->saveQuietly();
        }

        $request->setUserResolver(fn () => $account);
        $request->attributes->set('mobile_token', $token);

        return $next($request);
    }

    /**
     * Perpanjang masa berlaku token selama perangkat masih dipakai, supaya pengingat/push tidak berhenti diam-diam
     * tepat saat pegawai masih aktif. Batas mutlak (mobile.token_max_days sejak login) tetap berlaku, dan
     * penulisan hanya terjadi bila perpanjangannya berarti (lebih dari sehari), bukan di setiap permintaan.
     *
     * @return array{expires_at?: \Carbon\Carbon}
     */
    private function slidingExpiry(MobileToken $token): array
    {
        if (! config('mobile.sliding_expiry') || ! $token->expires_at) {
            return [];
        }

        $next = now()->addDays(max(1, (int) config('mobile.token_ttl_days', 30)));
        $cap = $token->created_at?->copy()->addDays(max(1, (int) config('mobile.token_max_days', 180)));
        if ($cap && $next->gt($cap)) {
            $next = $cap;
        }

        return $next->gt($token->expires_at->copy()->addDay()) ? ['expires_at' => $next] : [];
    }
}
