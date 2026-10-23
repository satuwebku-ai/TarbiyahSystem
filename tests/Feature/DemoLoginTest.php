<?php

namespace Tests\Feature;

use App\Services\DemoAccounts;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman / (Coming soon), /demo, dan tombol "Akun Demo" (login satu klik). Mode routing "path". */
class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['domains.demo_login' => true]);
        $this->seed(SuperAdminSeeder::class);
    }

    public function test_root_shows_coming_soon_without_login_buttons(): void
    {
        $this->get('/')->assertOk()->assertSee('Coming soon')->assertDontSee('Login Pegawai')->assertDontSee('Login Pelajar');
    }

    public function test_demo_page_links_to_all_three_logins(): void
    {
        $this->get('/demo')->assertOk()
            ->assertSee('Login Pelajar')->assertSee('Login Pegawai')->assertSee('Login Pengelola')
            ->assertSee(route('pelajar.login'), false)->assertSee(route('pegawai.login'), false)->assertSee(route('admin.login'), false);
    }

    public function test_admin_login_lists_the_super_admin_and_it_logs_in_to_the_dashboard(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Super Admin');

        $accounts = app(DemoAccounts::class)->for('admin');
        $this->assertCount(1, $accounts);
        $this->assertCount(0, app(DemoAccounts::class)->for('pegawai'));
        $this->assertCount(0, app(DemoAccounts::class)->for('pelajar'));

        $this->post(route('admin.demo.login'), ['account' => $accounts[0]['id']])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($accounts[0]['model'], 'admin');
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_pegawai_and_pelajar_logins_open_without_demo_accounts(): void
    {
        $this->get(route('pegawai.login'))->assertOk()->assertSee('Belum ada akun demo');
        $this->get(route('pelajar.login'))->assertOk()->assertSee('Belum ada akun demo');
    }

    public function test_only_listed_demo_accounts_can_be_used(): void
    {
        // 404 diubah handler aplikasi menjadi redirect; yang penting: tidak ada yang login.
        $this->post(route('admin.demo.login'), ['account' => 999999])->assertRedirect();
        $this->assertGuest('admin');
        $this->post(route('pelajar.demo.login'), ['account' => 1])->assertRedirect();
        $this->assertGuest('pelajar');
    }

    public function test_demo_login_is_disabled_when_flag_is_off(): void
    {
        config(['domains.demo_login' => false]);
        $this->assertSame([], app(DemoAccounts::class)->for('admin'));

        $this->get(route('admin.login'))->assertOk()->assertDontSee('Akun demo');
        $this->post(route('admin.demo.login'), ['account' => 1])->assertRedirect();
        $this->assertGuest('admin');
    }
}
