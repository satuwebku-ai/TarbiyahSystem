<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware GLOBAL (jalan paling awal, sebelum routing & middleware route).
 * Guard default ditentukan dari path (mode path) atau host (mode subdomain):
 *   pengelola.<domain> / admin/*   -> admin
 *   pegawai.<domain>   / pegawai/* -> pegawai
 *   selain itu                     -> pelajar
 * Sehingga auth, guest, can, @can, $request->user() selalu memakai guard yang sama.
 */
class UseGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        $host = $request->getHost();

        $isPath = fn (string $prefix) => $path === $prefix || str_starts_with($path, $prefix.'/');
        $isHost = fn (string $key) => strcasecmp($host, (string) config("domains.$key")) === 0;

        $guard = match (true) {
            $isPath('admin') => 'admin',
            $isPath('pelajar') => 'pelajar',
            $isPath('pegawai') => 'pegawai',
            $isHost('admin') => 'admin',
            $isHost('pegawai') => 'pegawai',
            default => 'pelajar',
        };
        Auth::shouldUse($guard);

        return $next($request);
    }
}
