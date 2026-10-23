<?php

use App\Http\Middleware\UseGuard;
use App\Http\Middleware\EnforceAdminSessionTimeout;
use App\Http\Middleware\EnforcePegawaiSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            $d = config('domains');

            // Mesin fingerprint (push ADMS): tanpa sesi/CSRF, di luar grup web.
            Route::group([], base_path('routes/iclock.php'));

            if (($d['routing_mode'] ?? 'path') === 'subdomain') {
                Route::middleware('web')
                    ->domain($d['admin'])->name('admin.')
                    ->group(base_path('routes/admin.php'));

                Route::middleware('web')
                    ->domain($d['pelajar'])->name('pelajar.')
                    ->group(base_path('routes/pelajar.php'));

                Route::middleware('web')
                    ->domain($d['pegawai'])->name('pegawai.')
                    ->group(base_path('routes/pegawai.php'));

                Route::middleware('web')
                    ->domain($d['root'])
                    ->group(base_path('routes/web.php'));
            } else {
                Route::middleware('web')
                    ->prefix('admin')->name('admin.')
                    ->group(base_path('routes/admin.php'));

                Route::middleware('web')
                    ->prefix('pelajar')->name('pelajar.')
                    ->group(base_path('routes/pelajar.php'));

                Route::middleware('web')
                    ->prefix('pegawai')->name('pegawai.')
                    ->group(base_path('routes/pegawai.php'));

                Route::middleware('web')->group(base_path('routes/web.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guard default per host (admin/pegawai/pelajar) — global, jalan sebelum middleware route.
        $middleware->prepend(UseGuard::class);

        // Di belakang reverse proxy/CDN (Cloudflare, nginx, dll.): isi TRUSTED_PROXIES di .env
        // (IP dipisah koma, atau * bila proxy tak bisa disebut) agar $request->ip() berisi IP klien asli.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        $middleware->alias([
            'admin.session' => EnforceAdminSessionTimeout::class,
            'pegawai.session' => EnforcePegawaiSession::class,
        ]);

        // Belum login -> halaman login sesuai subdomain.
        $middleware->redirectGuestsTo(fn (Request $r) => match (true) {
            $r->routeIs('admin.*') => route('admin.login'),
            $r->routeIs('pegawai.*') => route('pegawai.login'),
            default => route('pelajar.login'),
        });

        // Sudah login tapi buka /login -> dashboard sesuai subdomain.
        $middleware->redirectUsersTo(fn (Request $r) => match (true) {
            $r->routeIs('admin.*') => route('admin.dashboard'),
            $r->routeIs('pegawai.*') => route('pegawai.dashboard'),
            default => route('pelajar.dashboard'),
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
        // Hanya 404 (halaman/data tidak ada) dan 405 (metode salah).
        if (! in_array($e->getStatusCode(), [404, 405], true)) {
            return null;
        }

        // Jangan ganggu API/JSON, mesin fingerprint, dan file statis (favicon, gambar, dll).
        if ($request->expectsJson()
            || $request->is('iclock/*')
            || pathinfo($request->path(), PATHINFO_EXTENSION) !== '') {
            return null;
        }

        // Guard sudah ditentukan oleh UseGuard (admin / pegawai / pelajar).
        $dashboard = match (\Illuminate\Support\Facades\Auth::getDefaultDriver()) {
            'admin' => route('admin.dashboard'),
            'pegawai' => route('pegawai.dashboard'),
            default => route('pelajar.dashboard'),
        };

        // Cegah loop kalau dashboard sendiri yang error.
        if (rtrim($request->url(), '/') === rtrim($dashboard, '/')) {
            return null;
        }

        return redirect($dashboard)->with('error', 'Halaman yang Anda cari tidak ditemukan.');
    });
})->create();
