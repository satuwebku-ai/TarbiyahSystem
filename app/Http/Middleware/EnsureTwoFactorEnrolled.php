<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bila Kebijakan Keamanan mewajibkan 2FA untuk jenis akun ini dan akunnya belum mendaftar, semua halaman
 * diarahkan ke Profil sampai 2FA aktif. Profil, pendaftaran 2FA, dan logout tetap bisa dibuka.
 */
class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! TwoFactorPolicy::mustEnroll($user)
            || $request->routeIs('admin.profile.*', 'pegawai.profile.*', 'admin.logout', 'pegawai.logout')) {
            return $next($request);
        }

        return redirect(route(TwoFactorPolicy::guardOf($user).'.profile.edit').'#two-factor')
            ->with('error', 'Kebijakan keamanan mewajibkan autentikasi dua faktor (2FA). Aktifkan 2FA di bawah ini untuk melanjutkan.');
    }
}
