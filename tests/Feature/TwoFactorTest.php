<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\Employee;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** 2FA (TOTP): pendaftaran dari profil, tantangan saat login, kode pemulihan, kewajiban, dan reset oleh admin. */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Test-Password-2026!';

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function totp(): TwoFactorAuthenticator
    {
        return app(TwoFactorAuthenticator::class);
    }

    private function makeAdmin(array $permissions = ['dashboard.view'], bool $super = false): Admin
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => $super ? 'super-admin' : "tf-role-$n", 'label' => "Role $n", 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }
        $admin = Admin::create(['name' => "Admin $n", 'username' => "tf$n", 'email' => "tf$n@example.test", 'password' => self::PASSWORD, 'is_active' => true]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    /** Aktifkan 2FA langsung di database; kembalikan [rahasia, kode pemulihan polos]. */
    private function enroll(Admin|User $user): array
    {
        $secret = $this->totp()->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => null])->save();

        return [$secret, $user->regenerateRecoveryCodes()];
    }

    private function codeFor(string $secret, int $offset = 0): string
    {
        return $this->totp()->codeAt($secret, $this->totp()->currentStep() + $offset);
    }

    // ---- login ----------------------------------------------------------

    public function test_login_without_2fa_is_unchanged(): void
    {
        $admin = $this->makeAdmin();

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_correct_password_with_2fa_does_not_log_in_until_the_code_is_entered(): void
    {
        $admin = $this->makeAdmin();
        [$secret] = $this->enroll($admin);

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD, 'remember' => '1'])
            ->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest('admin');
        $this->assertNull($admin->fresh()->last_login_at);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee('Kode autentikasi');

        $this->post(route('admin.two-factor.challenge.store'), ['code' => $this->codeFor($secret)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertTrue(AdminAuditLog::where('event', 'admin.login_success')->where('actor_admin_id', $admin->id)->exists());
    }

    public function test_remember_cookie_is_not_issued_before_the_second_factor(): void
    {
        $admin = $this->makeAdmin();
        $this->enroll($admin);

        $response = $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD, 'remember' => '1']);

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringStartsNotWith('remember_admin_', $cookie->getName());
            $this->assertStringStartsNotWith('remember_web_', $cookie->getName());
        }
    }

    public function test_wrong_code_is_rejected_and_audited(): void
    {
        $admin = $this->makeAdmin();
        [$secret] = $this->enroll($admin);
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);

        $wrong = $this->codeFor($secret) === '000000' ? '111111' : '000000';
        $this->from(route('admin.two-factor.challenge'))
            ->post(route('admin.two-factor.challenge.store'), ['code' => $wrong])
            ->assertSessionHasErrors('code');
        $this->assertGuest('admin');
        $this->assertTrue(AdminAuditLog::where('event', 'admin.two_factor_failed')->exists());
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $admin = $this->makeAdmin();
        [$secret] = $this->enroll($admin);
        $code = $this->codeFor($secret);

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);
        $this->post(route('admin.two-factor.challenge.store'), ['code' => $code])->assertRedirect(route('admin.dashboard'));

        $this->post(route('admin.logout'));
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);
        $this->from(route('admin.two-factor.challenge'))
            ->post(route('admin.two-factor.challenge.store'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_recovery_code_logs_in_once_and_is_consumed(): void
    {
        $admin = $this->makeAdmin();
        [, $recovery] = $this->enroll($admin);

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);
        $this->post(route('admin.two-factor.challenge.store'), ['code' => strtoupper($recovery[0])])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame(count($recovery) - 1, $admin->fresh()->recoveryCodesRemaining());

        $this->post(route('admin.logout'));
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);
        $this->from(route('admin.two-factor.challenge'))
            ->post(route('admin.two-factor.challenge.store'), ['code' => $recovery[0]])
            ->assertSessionHasErrors('code');
    }

    public function test_too_many_wrong_codes_lock_the_challenge(): void
    {
        $admin = $this->makeAdmin();
        [$secret] = $this->enroll($admin);
        $max = AdminSecuritySetting::current()->max_login_attempts;
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);

        $wrong = $this->codeFor($secret) === '000000' ? '111111' : '000000';
        for ($i = 0; $i < $max; $i++) {
            $this->from(route('admin.two-factor.challenge'))->post(route('admin.two-factor.challenge.store'), ['code' => $wrong]);
        }

        // Percobaan berikutnya (walau kodenya benar) ditolak dan sesi verifikasi dibuang.
        $this->post(route('admin.two-factor.challenge.store'), ['code' => $this->codeFor($secret)])
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
    }

    public function test_challenge_page_requires_a_pending_login(): void
    {
        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.challenge.store'), ['code' => '123456'])->assertRedirect(route('admin.login'));
    }

    public function test_pending_login_expires(): void
    {
        $admin = $this->makeAdmin();
        [$secret] = $this->enroll($admin);
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD]);

        $this->travel(6)->minutes();
        $this->post(route('admin.two-factor.challenge.store'), ['code' => $this->codeFor($secret)])->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_wrong_password_never_reveals_that_the_account_has_2fa(): void
    {
        $admin = $this->makeAdmin();
        $this->enroll($admin);

        $this->from(route('admin.login'))->post(route('admin.login.store'), ['username' => $admin->username, 'password' => 'salah'])
            ->assertSessionHasErrors('username')->assertRedirect(route('admin.login'));
    }

    public function test_switching_the_feature_off_skips_the_challenge(): void
    {
        $admin = $this->makeAdmin();
        $this->enroll($admin);
        AdminSecuritySetting::query()->update(['two_factor_enabled' => false]);
        Cache::flush();

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    // ---- pendaftaran dari profil ---------------------------------------

    public function test_admin_enrolls_from_the_profile_page(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.profile.two-factor.enable'))->assertRedirect(route('admin.profile.edit').'#two-factor');
        $admin->refresh();
        $this->assertTrue($admin->hasPendingTwoFactorSetup());
        $this->assertFalse($admin->hasTwoFactorEnabled());

        $this->get(route('admin.profile.edit'))->assertOk()->assertSee($admin->two_factor_secret === null ? '' : substr(chunk_split($admin->two_factor_secret, 4, ' '), 0, 4));

        $this->post(route('admin.profile.two-factor.confirm'), ['two_factor_code' => '000000'])->assertSessionHasErrors('two_factor_code');
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());

        $response = $this->post(route('admin.profile.two-factor.confirm'), ['two_factor_code' => $this->codeFor($admin->two_factor_secret)]);
        $response->assertSessionHas('two_factor_recovery_codes');
        $this->assertCount(8, session('two_factor_recovery_codes'));
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(AdminAuditLog::where('event', 'admin.two_factor_enabled')->exists());
    }

    public function test_secret_and_recovery_hashes_are_never_stored_in_plain_text(): void
    {
        $admin = $this->makeAdmin();
        [$secret, $recovery] = $this->enroll($admin);

        $raw = \DB::table('admins')->where('id', $admin->id)->first();
        $this->assertStringNotContainsString($secret, (string) $raw->two_factor_secret);
        $this->assertStringNotContainsString($recovery[0], (string) $raw->two_factor_recovery_codes);
        $this->assertArrayNotHasKey('two_factor_secret', $admin->fresh()->toArray());
    }

    public function test_disabling_and_regenerating_need_the_password(): void
    {
        $admin = $this->makeAdmin();
        [, $old] = $this->enroll($admin);
        $this->actingAs($admin, 'admin');

        $this->delete(route('admin.profile.two-factor.disable'), ['two_factor_password' => 'salah'])->assertSessionHasErrors('two_factor_password');
        $this->post(route('admin.profile.two-factor.recovery-codes'), ['two_factor_password' => 'salah'])->assertSessionHasErrors('two_factor_password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->post(route('admin.profile.two-factor.recovery-codes'), ['two_factor_password' => self::PASSWORD])->assertSessionHas('two_factor_recovery_codes');
        $this->assertFalse($admin->fresh()->consumeRecoveryCode($old[0]), 'kode lama harus tidak berlaku');

        $this->delete(route('admin.profile.two-factor.disable'), ['two_factor_password' => self::PASSWORD])->assertSessionHas('success');
        $admin->refresh();
        $this->assertFalse($admin->hasTwoFactorEnabled());
        $this->assertNull($admin->two_factor_secret);
    }

    public function test_required_2fa_cannot_be_disabled_by_the_user(): void
    {
        $admin = $this->makeAdmin();
        $this->enroll($admin);
        AdminSecuritySetting::query()->update(['two_factor_required_admin' => true]);
        Cache::flush();
        $this->actingAs($admin, 'admin');

        $this->delete(route('admin.profile.two-factor.disable'), ['two_factor_password' => self::PASSWORD])->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
    }

    // ---- kewajiban ------------------------------------------------------

    public function test_required_policy_sends_unenrolled_admins_to_the_profile_until_they_enroll(): void
    {
        AdminSecuritySetting::query()->update(['two_factor_required_admin' => true]);
        Cache::flush();
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.profile.edit').'#two-factor');
        $this->get(route('admin.profile.edit'))->assertOk()->assertSee('mewajibkan');
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->actingAs($admin, 'admin');
        $this->enroll($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_policy_page_saves_settings_and_refuses_to_lock_out_the_current_admin(): void
    {
        $admin = $this->makeAdmin(['dashboard.view', 'security.view', 'security.update']);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.security.two-factor'))->assertOk()->assertSee('Kebijakan 2FA');

        // Mewajibkan 2FA admin saat akun sendiri belum ber-2FA ditolak.
        $this->put(route('admin.security.two-factor.update'), ['two_factor_enabled' => '1', 'two_factor_required_admin' => '1'])
            ->assertSessionHas('error');
        $this->assertFalse(AdminSecuritySetting::current()->two_factor_required_admin);

        $this->enroll($admin);
        $this->put(route('admin.security.two-factor.update'), ['two_factor_enabled' => '1', 'two_factor_required_admin' => '1', 'two_factor_required_pegawai' => '1'])
            ->assertSessionHas('success');
        Cache::flush();
        $settings = AdminSecuritySetting::current();
        $this->assertTrue($settings->two_factor_required_admin);
        $this->assertTrue($settings->two_factor_required_pegawai);
        $this->assertTrue(AdminAuditLog::where('event', 'security.two_factor_policy_updated')->exists());

        // Mematikan fitur ikut mematikan "wajib".
        $this->put(route('admin.security.two-factor.update'), [])->assertSessionHas('success');
        Cache::flush();
        $settings = AdminSecuritySetting::current();
        $this->assertFalse($settings->two_factor_enabled);
        $this->assertFalse($settings->two_factor_required_admin);
    }

    public function test_policy_page_needs_the_security_permissions(): void
    {
        $viewer = $this->makeAdmin(['dashboard.view', 'security.view']);
        $this->actingAs($viewer, 'admin');
        $this->get(route('admin.security.two-factor'))->assertOk();
        $this->put(route('admin.security.two-factor.update'), ['two_factor_enabled' => '1'])->assertForbidden();

        $nobody = $this->makeAdmin(['dashboard.view']);
        $this->actingAs($nobody, 'admin');
        $this->get(route('admin.security.two-factor'))->assertForbidden();
    }

    // ---- reset ----------------------------------------------------------

    public function test_admin_can_reset_another_accounts_2fa(): void
    {
        $manager = $this->makeAdmin(['dashboard.view', 'security.view', 'security.update']);
        $target = $this->makeAdmin();
        $this->enroll($target);
        $this->actingAs($manager, 'admin');

        $this->delete(route('admin.security.two-factor.reset', ['admin', $target->id]))->assertSessionHas('success');
        $this->assertFalse($target->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(AdminAuditLog::where('event', 'security.two_factor_reset')->where('subject_id', (string) $target->id)->exists());
    }

    public function test_only_super_admin_can_reset_a_super_admins_2fa(): void
    {
        $manager = $this->makeAdmin(['dashboard.view', 'security.view', 'security.update']);
        $super = $this->makeAdmin(['dashboard.view'], super: true);
        $this->enroll($super);
        $this->actingAs($manager, 'admin');

        $this->delete(route('admin.security.two-factor.reset', ['admin', $super->id]))->assertForbidden();
        $this->assertTrue($super->fresh()->hasTwoFactorEnabled());
    }

    public function test_recover_command_resets_an_account_and_disables_captcha(): void
    {
        $admin = $this->makeAdmin();
        $this->enroll($admin);

        $this->artisan('security:recover', ['--two-factor' => $admin->username])->assertSuccessful();
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());

        $this->artisan('security:recover', ['--two-factor' => 'tidak-ada'])->assertFailed();

        $this->artisan('security:recover', ['--captcha' => true])->assertSuccessful();
        $this->assertSame('off', app(\App\Services\AppSettings::class)->current('captcha_provider'));
    }

    // ---- portal pegawai --------------------------------------------------

    public function test_pegawai_portal_also_asks_for_the_code(): void
    {
        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $unit = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        $role = Role::create(['name' => 'pegawai-tf', 'label' => 'Pegawai', 'guard_name' => 'pegawai']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'attendance.self', 'guard_name' => 'pegawai'], ['label' => 'x'])->id);
        $user = User::create(['name' => 'Guru TF', 'username' => 'gurutf', 'email' => 'gurutf@example.test', 'password' => self::PASSWORD, 'is_active' => true]);
        $user->syncRoles([$role->id]);
        $employee = Employee::create(['employee_number' => 'E-TF', 'name' => 'Guru TF', 'employee_type' => 'teacher', 'status' => EmployeeStatus::Active, 'user_id' => $user->id]);
        $position = OrganizationalPosition::create(['organizational_unit_id' => $unit->id, 'name' => 'Guru', 'is_active' => true]);
        $employee->assignments()->create(['organizational_unit_id' => $unit->id, 'organizational_position_id' => $position->id, 'is_primary' => true, 'start_date' => '2026-01-01']);
        [$secret] = $this->enroll($user);

        $this->post(route('pegawai.login.store'), ['username' => 'gurutf', 'password' => self::PASSWORD])
            ->assertRedirect(route('pegawai.two-factor.challenge'));
        $this->assertGuest('pegawai');

        $this->post(route('pegawai.two-factor.challenge.store'), ['code' => $this->codeFor($secret)])
            ->assertRedirect(route('pegawai.dashboard'));
        $this->assertAuthenticatedAs($user, 'pegawai');
    }

    public function test_pelajar_portal_has_no_2fa(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('pelajar.two-factor.challenge'));
    }

    // ---- TOTP murni -----------------------------------------------------

    public function test_totp_matches_the_rfc_6238_vectors_and_rejects_replays(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
        $this->assertSame('287082', $this->totp()->codeAt($secret, intdiv(59, 30)));
        $this->assertSame('081804', $this->totp()->codeAt($secret, intdiv(1111111109, 30)));

        $this->assertSame(1, $this->totp()->verify($secret, '287 082', null, 59));
        $this->assertNull($this->totp()->verify($secret, '287082', 1, 59), 'langkah yang sudah dipakai ditolak');
        $this->assertNull($this->totp()->verify($secret, '287082', null, 59 + 120), 'di luar jendela waktu');
        $this->assertNull($this->totp()->verify($secret, 'abcdef', null, 59));
    }
}
