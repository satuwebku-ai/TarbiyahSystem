<?php

namespace App\Http\Middleware;

use App\Services\Captcha;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan standar: anti-clickjacking, anti-MIME-sniffing, HSTS, Referrer/Permissions-Policy, dan CSP.
 *
 * CSP masih mengizinkan skrip/gaya inline karena banyak tampilan memakainya; yang diblokir adalah
 * skrip dari host selain yang terdaftar di config/security.php, <object>/<embed>, <base> palsu, dan
 * penyematan halaman di situs lain (frame-ancestors).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers')) {
            return $response;
        }

        $headers = $response->headers;
        $set = function (string $name, string $value) use ($headers): void {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        };

        $set('X-Content-Type-Options', 'nosniff');
        $set('X-Frame-Options', 'SAMEORIGIN');
        $set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Kamera (selfie absen) dan lokasi (geofence) hanya untuk situs ini.
        $set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=(), payment=(), usb=()');

        if (config('security.hsts') && $request->isSecure() && app()->isProduction()) {
            $set('Strict-Transport-Security', 'max-age='.(int) config('security.hsts_max_age'));
        }

        $mode = config('security.csp');
        if ($mode !== 'off') {
            $set($mode === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    private function csp(): string
    {
        // Host CAPTCHA (Turnstile/reCAPTCHA/hCaptcha) hanya ditambahkan saat provider itu aktif dan kuncinya lengkap.
        $captcha = app(Captcha::class);
        $src = fn (string $type): string => implode(' ', array_unique([
            ...(array) config("security.sources.{$type}", []),
            ...$captcha->cspSources($type),
        ]));
        $frames = $captcha->cspSources('frame');

        return implode('; ', array_filter([
            "default-src 'self'",
            trim("script-src 'self' 'unsafe-inline' ".$src('script')),
            trim("style-src 'self' 'unsafe-inline' ".$src('style')),
            trim("font-src 'self' data: ".$src('font')),
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            trim("connect-src 'self' ".implode(' ', $captcha->cspSources('connect'))),
            $frames ? "frame-src 'self' ".implode(' ', $frames) : null,
            "object-src 'none'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
        ]));
    }
}
