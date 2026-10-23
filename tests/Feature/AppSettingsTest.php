<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Menu Pengaturan Aplikasi: izin, penyimpanan, enkripsi rahasia, penerapan ke config(), dan pendaftaran pelajar. */
class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeAdmin(array $permissions): Admin
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => "set-role-$n", 'label' => "Role $n", 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }
        $admin = Admin::create(['name' => "Admin $n", 'username' => "set$n", 'email' => "set$n@example.test", 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    private function settingsAdmin(): Admin
    {
        return $this->makeAdmin(['dashboard.view', 'settings.view', 'settings.update']);
    }

    private function validEmailInput(array $override = []): array
    {
        return array_merge([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.test',
            'mail_port' => 587,
            'mail_scheme' => '',
            'mail_username' => 'mailer@example.test',
            'mail_password' => '',
            'mail_from_address' => 'no-reply@example.test',
            'mail_from_name' => 'Madrasah Uji',
            'notify_mail' => '1',
        ], $override);
    }

    // ---- izin ------------------------------------------------------------

    public function test_settings_pages_require_the_settings_permissions(): void
    {
        $nobody = $this->makeAdmin(['dashboard.view']);
        $viewer = $this->makeAdmin(['dashboard.view', 'settings.view']);

        $this->actingAs($nobody, 'admin');
        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->put(route('admin.settings.update', 'umum'), ['app_name' => 'X', 'attendance_timezone' => 'Asia/Jakarta'])->assertForbidden();
        $this->post(route('admin.settings.test-mail'), ['test_email' => 'a@example.test'])->assertForbidden();

        $this->actingAs($viewer, 'admin');
        $this->get(route('admin.settings.index'))->assertOk();
        $this->put(route('admin.settings.update', 'umum'), ['app_name' => 'X', 'attendance_timezone' => 'Asia/Jakarta'])->assertForbidden();
        $this->post(route('admin.settings.test-mail'), ['test_email' => 'a@example.test'])->assertForbidden();
        $this->assertSame(0, AppSetting::count());
    }

    public function test_menu_entry_is_shown_only_with_settings_view(): void
    {
        $this->actingAs($this->makeAdmin(['dashboard.view']), 'admin');
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Pengaturan Aplikasi');

        $this->actingAs($this->makeAdmin(['dashboard.view', 'settings.view']), 'admin');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Pengaturan Aplikasi');
    }

    public function test_permissions_sync_registers_the_settings_permissions_and_seeded_roles_do_not_get_them(): void
    {
        Artisan::call('permissions:sync');

        $this->assertTrue(Permission::where('guard_name', 'admin')->where('name', 'settings.view')->exists());
        $this->assertTrue(Permission::where('guard_name', 'admin')->where('name', 'settings.update')->exists());

        $this->seed(\Database\Seeders\RbacSeeder::class);
        $staff = Role::where('name', 'staf')->where('guard_name', 'admin')->first();
        $this->assertFalse($staff->permissions()->whereIn('name', ['settings.view', 'settings.update'])->exists());
    }

    public function test_super_admin_can_open_the_settings_page(): void
    {
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin'], ['label' => 'Super Admin']);
        $admin = Admin::create(['name' => 'Root', 'username' => 'rootset', 'email' => 'root-set@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);

        $this->actingAs($admin, 'admin')->get(route('admin.settings.index'))->assertOk()->assertSee('Pengaturan Aplikasi');
    }

    // ---- penyimpanan & audit ----------------------------------------------

    public function test_updating_a_group_stores_only_changed_values_applies_them_and_audits_key_names_only(): void
    {
        $admin = $this->settingsAdmin();
        $this->actingAs($admin, 'admin');

        $this->put(route('admin.settings.update', 'umum'), ['app_name' => 'Madrasah Baru', 'attendance_timezone' => config('attendance.timezone')])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'umum']))
            ->assertSessionHas('success');

        // timezone tidak berubah dari nilai berlaku => tetap mengikuti .env, tidak ditulis ke database
        $this->assertSame(['app_name'], AppSetting::pluck('key')->all());
        $this->assertSame('Madrasah Baru', AppSetting::where('key', 'app_name')->value('value'));

        $this->app->make(AppSettings::class)->apply();
        $this->assertSame('Madrasah Baru', config('app.name'));

        $log = AdminAuditLog::where('event', 'settings.updated')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(['app_name'], $log->details['changed_keys']);
        $this->assertStringNotContainsString('Madrasah Baru', json_encode($log->details));
    }

    public function test_saving_identical_values_changes_nothing_and_writes_no_audit_entry(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');

        $this->put(route('admin.settings.update', 'umum'), ['app_name' => config('app.name'), 'attendance_timezone' => config('attendance.timezone')])
            ->assertSessionHas('success', 'Tidak ada perubahan pada pengaturan.');

        $this->assertSame(0, AppSetting::count());
        $this->assertFalse(AdminAuditLog::where('event', 'settings.updated')->exists());
    }

    public function test_invalid_input_is_rejected_and_nothing_is_stored(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');

        $this->put(route('admin.settings.update', 'umum'), ['app_name' => '', 'attendance_timezone' => 'Mars/Olympus'])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'umum']))
            ->assertSessionHasErrors(['app_name', 'attendance_timezone']);

        $this->put(route('admin.settings.update', 'fingerprint'), [
            'fingerprint_max_future_minutes' => -1,
            'fingerprint_max_punch_age_days' => 0,
            'fingerprint_max_punches_per_request' => 'banyak',
            'fingerprint_max_body_bytes' => 10,
        ])->assertSessionHasErrors(['fingerprint_max_future_minutes', 'fingerprint_max_punch_age_days', 'fingerprint_max_punches_per_request', 'fingerprint_max_body_bytes']);

        $this->put(route('admin.settings.update', 'email'), $this->validEmailInput(['mail_host' => '', 'mail_from_address' => 'bukan-email']))
            ->assertSessionHasErrors(['mail_host', 'mail_from_address']);

        $this->assertSame(0, AppSetting::count());
    }

    public function test_unknown_group_is_not_found_and_only_defined_keys_are_ever_stored(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');

        $this->putJson(route('admin.settings.update', 'rahasia'), ['app_key' => 'base64:x'])->assertNotFound();
        $this->put(route('admin.settings.update', 'umum'), ['app_name' => 'Aman', 'attendance_timezone' => 'Asia/Jakarta', 'app_debug' => '1', 'app_key' => 'base64:x']);

        $this->assertEmpty(AppSetting::whereIn('key', ['app_debug', 'app_key'])->get());
    }

    public function test_fingerprint_limits_are_saved_and_applied(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');

        $this->put(route('admin.settings.update', 'fingerprint'), [
            'fingerprint_max_future_minutes' => 5,
            'fingerprint_max_punch_age_days' => 30,
            'fingerprint_max_punches_per_request' => 500,
            'fingerprint_max_body_bytes' => 131072,
        ])->assertSessionHas('success');

        $this->app->make(AppSettings::class)->apply();
        $this->assertSame(5, config('student_attendance.max_future_minutes'));
        $this->assertSame(30, config('student_attendance.max_punch_age_days'));
        $this->assertSame(500, config('student_attendance.max_punches_per_request'));
        $this->assertSame(131072, config('student_attendance.max_body_bytes'));
    }

    // ---- rahasia -----------------------------------------------------------

    public function test_smtp_password_is_encrypted_never_shown_never_logged_and_kept_when_left_blank(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');
        $secret = 'smtp-Rahasia-Sangat-Unik-9137';

        $this->put(route('admin.settings.update', 'email'), $this->validEmailInput(['mail_password' => $secret]))
            ->assertSessionHas('success');

        $row = AppSetting::where('key', 'mail_password')->first();
        $this->assertTrue($row->is_secret);
        $this->assertNotSame($secret, $row->value);
        $this->assertStringNotContainsString($secret, (string) $row->value);
        $this->assertSame($secret, Crypt::decryptString($row->value));

        // yang masuk cache juga tidak boleh berupa teks asli
        $this->assertStringNotContainsString($secret, json_encode(Cache::get('app_settings.rows')));

        $this->app->make(AppSettings::class)->apply();
        $this->assertSame($secret, config('mail.mailers.smtp.password'));

        $page = $this->get(route('admin.settings.index', ['tab' => 'email']))->assertOk();
        $this->assertStringNotContainsString($secret, $page->getContent());

        foreach (AdminAuditLog::all() as $log) {
            $this->assertStringNotContainsString($secret, json_encode($log->details));
        }
        $this->assertContains('mail_password', AdminAuditLog::where('event', 'settings.updated')->latest('id')->first()->details['changed_keys']);

        // kolom password kosong = jangan ubah
        $this->put(route('admin.settings.update', 'email'), $this->validEmailInput(['mail_password' => '', 'mail_from_name' => 'Nama Lain']))
            ->assertSessionHas('success');
        $this->assertSame($secret, Crypt::decryptString(AppSetting::where('key', 'mail_password')->value('value')));
    }

    public function test_a_secret_that_cannot_be_decrypted_falls_back_to_the_env_value_instead_of_breaking_boot(): void
    {
        AppSetting::create(['key' => 'mail_password', 'value' => 'bukan-ciphertext-valid', 'is_secret' => true]);
        AppSetting::create(['key' => 'app_name', 'value' => 'Tetap Terbaca', 'is_secret' => false]);
        config(['mail.mailers.smtp.password' => 'dari-env']);

        $this->app->make(AppSettings::class)->flush();
        $this->app->make(AppSettings::class)->apply();

        $this->assertSame('dari-env', config('mail.mailers.smtp.password'));
        $this->assertSame('Tetap Terbaca', config('app.name'));
    }

    // ---- ketahanan saat boot ------------------------------------------------

    public function test_apply_does_nothing_and_does_not_fail_when_the_table_does_not_exist(): void
    {
        Schema::drop('app_settings');
        Cache::flush();
        $name = config('app.name');

        (new AppSettings())->apply();

        $this->assertSame($name, config('app.name'));
        $this->assertNull(Cache::get('app_settings.rows'), 'Hasil kosong tidak boleh di-cache saat tabel belum ada.');
    }

    // ---- pendaftaran pelajar --------------------------------------------------

    public function test_student_registration_can_be_closed_and_reopened_from_settings(): void
    {
        $admin = $this->settingsAdmin();

        config(['domains.pelajar_registration' => false]);
        $this->getJson(route('pelajar.register'))->assertNotFound();
        $this->postJson(route('pelajar.register.store'), [])->assertNotFound();

        config(['domains.pelajar_registration' => true]);
        $this->get(route('pelajar.register'))->assertOk();

        // dari menu: matikan lalu terapkan
        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.update', 'pendaftaran'), [])   // checkbox tidak dicentang = mati
            ->assertSessionHas('success');
        $this->assertSame('0', AppSetting::where('key', 'pelajar_registration')->value('value'));

        $this->app->make(AppSettings::class)->apply();
        $this->assertFalse(config('domains.pelajar_registration'));
        $this->getJson(route('pelajar.register'))->assertNotFound();
    }

    // ---- email uji --------------------------------------------------------------

    public function test_test_mail_is_sent_with_the_mail_driver_and_audited_without_the_address(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');
        config(['mail.default' => 'array']);

        $this->post(route('admin.settings.test-mail'), ['test_email' => 'tujuan-uji@example.test'])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'email']))
            ->assertSessionHas('success');

        $this->assertTrue(AdminAuditLog::where('event', 'settings.test_mail_sent')->exists());
        $this->assertStringNotContainsString('tujuan-uji@example.test', json_encode(AdminAuditLog::where('event', 'settings.test_mail_sent')->first()->details));
    }

    public function test_test_mail_refuses_to_pretend_when_the_mailer_is_log_and_validates_the_address(): void
    {
        $this->actingAs($this->settingsAdmin(), 'admin');

        config(['mail.default' => 'log']);
        $this->post(route('admin.settings.test-mail'), ['test_email' => 'a@example.test'])->assertSessionHas('error');
        $this->assertFalse(AdminAuditLog::where('event', 'settings.test_mail_sent')->exists());

        $this->post(route('admin.settings.test-mail'), ['test_email' => 'bukan-email'])->assertSessionHasErrors('test_email');
    }
}
