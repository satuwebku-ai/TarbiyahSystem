<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pendaftaran mandiri pelajar dibuka/ditutup dari menu Pengaturan, jadi dicek saat request
 * (bukan saat route didaftarkan): rutenya selalu ada, tetapi 404 selama pendaftaran ditutup.
 */
class EnsurePelajarRegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('domains.pelajar_registration'), 404);

        return $next($request);
    }
}
