<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * PWA portal pegawai: manifest, service worker, dan halaman offline. Semuanya publik (tanpa data pengguna).
 * Service worker sengaja TIDAK menyimpan halaman portal (berisi data pribadi); ia hanya menampilkan halaman
 * offline saat jaringan putus. Absen tetap butuh internet (lokasi, foto, dan pencatatan di server).
 */
class PwaController extends Controller
{
    /** Versi cache service worker; naikkan bila berkas yang di-cache berubah. */
    private const VERSION = 'v1';

    /** Path portal (tanpa host), mis. "/pegawai/" pada mode path atau "/" pada mode subdomain. */
    private function scope(): string
    {
        return rtrim(route('pegawai.dashboard', [], false), '/').'/';
    }

    public function manifest(): JsonResponse
    {
        $icon = fn (string $file, string $size, string $purpose = 'any') => [
            'src' => asset("assets/pwa/{$file}"), 'sizes' => $size, 'type' => 'image/png', 'purpose' => $purpose,
        ];

        return response()->json([
            'name' => config('app.name', 'TarbiyahSystem').' — Portal Pegawai',
            'short_name' => 'Absensi',
            'description' => 'Absensi pegawai dan guru: check-in, check-out, riwayat, dan koreksi.',
            'id' => $this->scope(),
            'start_url' => route('pegawai.dashboard', [], false),
            'scope' => $this->scope(),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'lang' => 'id',
            'background_color' => '#ffffff',
            'theme_color' => '#6366F1',
            'icons' => [
                $icon('icon-192.png', '192x192'),
                $icon('icon-512.png', '512x512'),
                $icon('icon-maskable-512.png', '512x512', 'maskable'),
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache']);
    }

    public function serviceWorker(): Response
    {
        return response()->view('pegawai.pwa.sw', [
            'version' => self::VERSION,
            'offlineUrl' => route('pegawai.pwa.offline', [], false),
            'precache' => [asset('assets/pwa/icon-192.png')],
        ], 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            // Selalu cek versi terbaru service worker agar pembaruan sampai ke pengguna.
            'Cache-Control' => 'no-cache, max-age=0',
        ]);
    }

    public function offline(): View
    {
        return view('pegawai.pwa.offline', ['homeUrl' => route('pegawai.dashboard', [], false)]);
    }
}
