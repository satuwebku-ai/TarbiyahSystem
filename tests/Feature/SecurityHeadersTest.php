<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_pages_send_security_headers_and_a_csp_that_keeps_the_app_working(): void
    {
        $r = $this->get('/admin/login')->assertOk();

        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $r->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=(self)', $r->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('geolocation=(self)', $r->headers->get('Permissions-Policy'));

        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        // Sumber yang dipakai tampilan harus tetap diizinkan, kalau tidak halaman kosong/rusak.
        foreach (['https://cdn.jsdelivr.net', 'https://code.jquery.com', 'https://cdn.datatables.net'] as $host) {
            $this->assertStringContainsString($host, $csp);
        }
        $this->assertStringContainsString('blob:', $csp); // pratinjau selfie
    }

    public function test_every_host_loaded_by_the_views_is_allowed_by_the_csp(): void
    {
        $allowed = collect(config('security.sources'))->flatten()->all();
        $used = collect(glob(resource_path('views/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE))
            ->flatMap(function ($file) {
                preg_match_all('#<(?:script|link)[^>]+(?:src|href)="(https?://[^/"]+)#i', (string) file_get_contents($file), $m);

                return $m[1];
            })->unique()->values()->all();

        $this->assertNotEmpty($used);
        $this->assertSame([], array_values(array_diff($used, $allowed)), 'CDN baru di tampilan harus didaftarkan di config/security.php.');
    }

    public function test_hsts_is_sent_only_over_https_in_production(): void
    {
        $this->call('GET', 'https://localhost/admin/login')->assertHeaderMissing('Strict-Transport-Security'); // bukan production

        $this->app['env'] = 'production';
        $this->call('GET', 'https://localhost/admin/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000');
        $this->call('GET', 'http://localhost/admin/login')->assertHeaderMissing('Strict-Transport-Security'); // http biasa
    }

    public function test_headers_and_csp_can_be_switched_off_or_set_to_report_only(): void
    {
        config(['security.csp' => 'report-only']);
        $r = $this->get('/admin/login');
        $this->assertNull($r->headers->get('Content-Security-Policy'));
        $this->assertNotNull($r->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.headers' => false]);
        $this->get('/admin/login')->assertHeaderMissing('X-Frame-Options')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_one_click_demo_login_no_longer_exists(): void
    {
        foreach (['admin', 'pegawai', 'pelajar'] as $area) {
            // Rute tidak ada lagi: tidak ada yang bisa login lewatnya, tetap tamu.
            $this->post("/{$area}/demo-login", ['account' => 1]);
            $this->assertGuest($area);
            $this->get("/{$area}/login")->assertOk()->assertDontSee('Akun demo');
        }

        $this->assertSame([], collect(Route::getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_ends_with($n, 'demo.login'))->values()->all());
    }

    public function test_pelajar_self_registration_is_closed_by_default(): void
    {
        $this->assertFalse((bool) config('domains.pelajar_registration'));
        $this->assertNotSame(200, $this->get('/pelajar/register')->getStatusCode()); // formulir tidak tampil
        $this->get('/pelajar/login')->assertOk()->assertDontSee(route('pelajar.register'));
    }
}
