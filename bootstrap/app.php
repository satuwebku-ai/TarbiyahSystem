<?php

use App\Http\Middleware\UseGuard;
use App\Http\Middleware\EnforceAdminSessionTimeout;
use App\Http\Middleware\AuthenticateMobileToken;
use App\Http\Middleware\EnforcePegawaiSession;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
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

            // API aplikasi Node fingerprint di sekolah: Basic auth + X-Node-Token, tanpa sesi/CSRF.
            Route::middleware(['api', 'mobile.json'])->prefix('api/fingerprint-node')->name('fingerprint-node.')
                ->group(base_path('routes/fingerprint-node.php'));

            // API aplikasi mobile: token Bearer, tanpa sesi/CSRF, dapat dipanggil dari host mana pun.
            Route::middleware(['api', 'mobile.json'])->prefix('api/mobile')->name('mobile.')
                ->group(base_path('routes/mobile.php'));

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

        // Proxy tepercaya dibaca dari config/trustedproxy.php (TRUSTED_PROXIES di .env); env() di sini
        // bernilai null setelah config:cache sehingga pengaturannya diabaikan diam-diam.

        // Ganti/reset password (juga oleh admin lain) otomatis mengakhiri sesi lain akun itu:
        // sesi menyimpan hash password dan dicek di tiap request. Memakai guard default dari UseGuard.
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin.session' => EnforceAdminSessionTimeout::class,
            'pegawai.session' => EnforcePegawaiSession::class,
            'two-factor.enrolled' => EnsureTwoFactorEnrolled::class,
            'mobile.auth' => AuthenticateMobileToken::class,
            'mobile.json' => ForceJsonResponse::class,
            'fingerprint.node' => \App\Http\Middleware\AuthenticateFingerprintNode::class,
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
    // Galat tak terduga pada permintaan DataTables (ada parameter "draw") dibalas JSON agar tabel
    // menampilkan pesan + tombol "Coba lagi", bukan indikator "Memuat" yang macet. Tetap dicatat di log.
    $exceptions->render(function (\Throwable $e, Request $request) {
        if (! $request->has('draw') || ! $request->expectsJson()) {
            return null;
        }
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            || $e instanceof \Illuminate\Validation\ValidationException
            || $e instanceof \Illuminate\Auth\AuthenticationException
            || $e instanceof \Illuminate\Auth\Access\AuthorizationException
            || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return null;
        }

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => config('app.debug')
                ? $e->getMessage()
                : 'Terjadi kesalahan di server (pukul '.now()->format('H:i:s').'). Hubungi admin bila berulang.',
        ], 500);
    });

    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
        // Hanya 404 (halaman/data tidak ada) dan 405 (metode salah).
        if (! in_array($e->getStatusCode(), [404, 405], true)) {
            return null;
        }

        // Jangan ganggu API/JSON, mesin fingerprint, dan file statis (favicon, gambar, dll).
        if ($request->expectsJson()
            || $request->is('iclock/*')
            || $request->is('api/*')
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
