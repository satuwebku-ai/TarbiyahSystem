<?php

namespace App\Http\Middleware;

use App\Models\FingerprintNodeConnection;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi aplikasi Node fingerprint: Authorization: Basic base64(username:password)
 * ditambah header X-Node-Token. Ketiganya harus cocok pada satu koneksi sekolah.
 */
class AuthenticateFingerprintNode
{
    private const MAX_FAILURES = 20;

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $key = 'fp-node-fail:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            return $this->error('Terlalu banyak percobaan gagal. Coba lagi sebentar lagi.', 429);
        }

        $token = trim((string) $request->header('X-Node-Token'));
        $username = (string) $request->getUser();
        $password = (string) $request->getPassword();

        $connection = ($token !== '' && $username !== '' && $password !== '')
            ? FingerprintNodeConnection::authenticate($username, $password, $token)
            : null;

        if (! $connection) {
            RateLimiter::hit($key, 60);

            return $this->error('Kredensial tidak valid.', 401);
        }

        if (! $connection->is_active) {
            return $this->error('Koneksi sekolah ini dinonaktifkan di panel.', 403);
        }

        if (! $connection->allowsIp($request->ip())) {
            return $this->error('IP pengirim tidak diizinkan.', 403);
        }

        $request->attributes->set('fingerprint_node', $connection);

        return $next($request);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $status);
    }
}
