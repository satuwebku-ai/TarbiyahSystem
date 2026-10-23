<?php

namespace Tests\Feature;

use App\Services\DemoAccounts;
use Database\Seeders\DemoSeeder;
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
        $this->seed(DemoSeeder::class);
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

    public function test_admin_login_lists_five_demo_accounts_in_hierarchy_order(): void
    {
        $this->get(route('admin.login'))->assertOk()
            ->assertSeeInOrder(['Super Admin', 'Pengurus Besar', 'Komisaris Wilayah', 'Komisaris Daerah', 'Kepala Sekolah']);

        $this->assertCount(5, app(DemoAccounts::class)->for('admin'));
        $this->assertCount(1, app(DemoAccounts::class)->for('pegawai'));
        $this->assertCount(1, app(DemoAccounts::class)->for('pelajar'));
    }

    public function test_every_admin_demo_account_logs_in_to_the_dashboard(): void
    {
        foreach (app(DemoAccounts::class)->for('admin') as $item) {
            $this->post(route('admin.demo.login'), ['account' => $item['id']])->assertRedirect(route('admin.dashboard'));
            $this->assertAuthenticatedAs($item['model'], 'admin');
            $this->get(route('admin.dashboard'))->assertOk();
            $this->post(route('admin.logout'));
        }
    }

    public function test_pegawai_demo_account_logs_in_to_the_portal(): void
    {
        $this->get(route('pegawai.login'))->assertOk()->assertSee('Pegawai / Guru');
        $item = app(DemoAccounts::class)->for('pegawai')[0];

        $this->post(route('pegawai.demo.login'), ['account' => $item['id']])->assertRedirect(route('pegawai.dashboard'));
        $this->assertAuthenticatedAs($item['model'], 'pegawai');
        $this->get(route('pegawai.dashboard'))->assertOk();
    }

    public function test_pelajar_demo_account_logs_in_to_the_dashboard(): void
    {
        $this->get(route('pelajar.login'))->assertOk()->assertSee('1234567890');
        $item = app(DemoAccounts::class)->for('pelajar')[0];

        $this->post(route('pelajar.demo.login'), ['account' => $item['id']])->assertRedirect(route('pelajar.dashboard'));
        $this->assertAuthenticatedAs($item['model'], 'pelajar');
        $this->get(route('pelajar.dashboard'))->assertOk();
    }

    public function test_only_listed_demo_accounts_can_be_used(): void
    {
        // 404 diubah handler aplikasi menjadi redirect; yang penting: tidak ada yang login.
        $this->post(route('admin.demo.login'), ['account' => 999999])->assertRedirect();
        $this->assertGuest('admin');

        // id akun pelajar tidak boleh dipakai di guard admin
        $pelajar = app(DemoAccounts::class)->for('pelajar')[0];
        $admins = collect(app(DemoAccounts::class)->for('admin'))->pluck('id');
        if (! $admins->contains($pelajar['id'])) {
            $this->post(route('admin.demo.login'), ['account' => $pelajar['id']])->assertRedirect();
            $this->assertGuest('admin');
        }
    }

    public function test_demo_login_is_disabled_when_flag_is_off(): void
    {
        config(['domains.demo_login' => false]);
        $item = app(DemoAccounts::class)->for('pelajar');
        $this->assertSame([], $item);

        $this->get(route('pelajar.login'))->assertOk()->assertDontSee('Akun demo');
        $this->post(route('pelajar.demo.login'), ['account' => 1])->assertRedirect();
        $this->assertGuest('pelajar');
    }
}
