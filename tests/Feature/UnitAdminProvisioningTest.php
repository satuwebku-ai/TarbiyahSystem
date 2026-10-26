<?php

namespace Tests\Feature;

use App\Console\Commands\ProvisionUnitAdmins;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserUnitAccess;
use App\Rules\AdminPasswordPolicy;
use App\Services\UnitAdminProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Akun admin per unit di struktur organisasi: rencana, pembuatan, hak akses, halaman web, dan perintah CLI. */
class UnitAdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnit $komwil;

    private OrganizationalUnit $komda;

    private OrganizationalUnit $sek1;

    private OrganizationalUnit $sek2;

    private OrganizationalUnit $sek3;

    private OrganizationalUnit $majelis;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $type = [];
        foreach (['majelis', 'komwil', 'komda', 'sekolah'] as $slug) {
            $type[$slug] = OrganizationalUnitType::create(['name' => ucfirst($slug), 'slug' => $slug, 'is_active' => true]);
        }
        foreach (['komisaris-wilayah' => 'Komisaris Wilayah', 'komisaris-daerah' => 'Komisaris Daerah', 'kepala-sekolah' => 'Kepala Sekolah'] as $name => $label) {
            Role::create(['name' => $name, 'label' => $label, 'guard_name' => 'admin']);
        }

        $unit = fn (string $t, string $code, string $name, ?OrganizationalUnit $parent = null, string $status = 'active') => OrganizationalUnit::create([
            'type_id' => $type[$t]->id, 'parent_id' => $parent?->id, 'code' => $code, 'name' => $name, 'status' => $status,
        ]);
        $this->komwil = $unit('komwil', 'KOMWIL-SULTENG', 'KOMWIL Sulawesi Tengah');
        $this->komda = $unit('komda', 'KOMDA-PALU', 'KOMDA Palu', $this->komwil);
        $this->sek1 = $unit('sekolah', 'SEK-1', 'SD Tarbiyah 1', $this->komda);
        $this->sek2 = $unit('sekolah', 'SEK-2', 'SD Tarbiyah 2', $this->komda, 'inactive');
        $this->sek3 = $unit('sekolah', 'SEK-3', 'SD Tarbiyah 3', $this->komda);
        $this->majelis = $unit('majelis', 'MAJELIS-01', 'Majelis Pendidikan');

        // SEK-1 sudah punya kepala sekolah; username "sek-3" sudah dipakai akun lain (harus menghasilkan sek-3-2).
        $this->makeAdmin('existing-kepsek', 'kepala-sekolah', $this->sek1);
        $this->makeAdmin('sek-3', null, null);
    }

    private function makeAdmin(string $username, ?string $role, ?OrganizationalUnit $scope): Admin
    {
        $admin = Admin::create(['name' => ucfirst($username), 'username' => $username, 'email' => "$username@example.test", 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        if ($role) {
            $admin->syncRoles([Role::where('guard_name', 'admin')->where('name', $role)->value('id')]);
        }
        if ($scope) {
            UserUnitAccess::create(['organizational_unit_id' => $scope->id, 'model_type' => Admin::class, 'model_id' => $admin->id, 'created_at' => now()]);
        }

        return $admin;
    }

    private function superAdmin(): Admin
    {
        $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'admin'], ['label' => 'Super Admin']);
        $admin = $this->makeAdmin('super'.++$this->seq, null, null);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    private function adminWithCreate(): Admin
    {
        $role = Role::create(['name' => 'pengelola-akun-'.++$this->seq, 'label' => 'Pengelola Akun', 'guard_name' => 'admin']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'admins.create', 'guard_name' => 'admin'], ['label' => 'admins.create'])->id);
        $admin = $this->makeAdmin('pengelola'.$this->seq, null, null);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    private function statuses(array $plan): array
    {
        return collect($plan)->mapWithKeys(fn ($r) => [$r['unit']->code => $r['status']])->all();
    }

    // ---- rencana ---------------------------------------------------------

    public function test_plan_decides_status_per_unit_and_builds_unique_usernames(): void
    {
        $plan = app(UnitAdminProvisioner::class)->plan();

        $this->assertSame([
            'KOMWIL-SULTENG' => 'create', 'KOMDA-PALU' => 'create',
            'SEK-1' => 'exists',    // sudah punya kepala sekolah
            'SEK-2' => 'inactive',  // unit nonaktif
            'SEK-3' => 'create',
        ], $this->statuses($plan)); // majelis tidak punya peran bawaan: tidak muncul

        $byCode = collect($plan)->keyBy(fn ($r) => $r['unit']->code);
        $this->assertSame('komwil-sulteng', $byCode['KOMWIL-SULTENG']['username']);
        $this->assertSame('sek-3-2', $byCode['SEK-3']['username']); // "sek-3" sudah dipakai
        $this->assertSame('sek-3-2@admin.invalid', $byCode['SEK-3']['email']);
        $this->assertSame('komisaris-wilayah', $byCode['KOMWIL-SULTENG']['role']->name);
        $this->assertSame('kepala-sekolah', $byCode['SEK-3']['role']->name);
        $this->assertNull($byCode['SEK-1']['username']);

        $custom = app(UnitAdminProvisioner::class)->plan(['sekolah'], 'Sekolah.sch.id');
        $this->assertSame(['SEK-1', 'SEK-2', 'SEK-3'], collect($custom)->map(fn ($r) => $r['unit']->code)->all());
        $this->assertSame('sek-3-2@sekolah.sch.id', collect($custom)->firstWhere(fn ($r) => $r['unit']->code === 'SEK-3')['email']);
    }

    public function test_plan_flags_missing_roles(): void
    {
        Role::where('name', 'kepala-sekolah')->delete();

        $this->assertSame('role_missing', $this->statuses(app(UnitAdminProvisioner::class)->plan(['sekolah']))['SEK-3']);
    }

    // ---- pembuatan -------------------------------------------------------

    public function test_provision_creates_scoped_accounts_with_policy_compliant_hashed_passwords_and_is_idempotent(): void
    {
        $provisioner = app(UnitAdminProvisioner::class);
        $before = Admin::count();

        $created = $provisioner->provision($provisioner->plan(), null);

        $this->assertCount(3, $created);
        $this->assertSame($before + 3, Admin::count());
        foreach ($created as $c) {
            $admin = Admin::where('username', $c['username'])->firstOrFail();
            $this->assertTrue($admin->is_active);
            $this->assertTrue(Hash::check($c['password'], $admin->password));
            $this->assertNotSame($c['password'], $admin->password);
            $this->assertTrue(Validator::make(['p' => $c['password']], ['p' => [AdminPasswordPolicy::rule()]])->passes());
            $this->assertSame([$c['role']->name], $admin->roles()->pluck('name')->all());
            $this->assertSame([$c['unit']->id], UserUnitAccess::where('model_type', Admin::class)->where('model_id', $admin->id)->pluck('organizational_unit_id')->map(fn ($i) => (int) $i)->all());
        }
        $this->assertCount(3, array_unique(array_column($created, 'password')));
        $this->assertSame(3, AdminAuditLog::where('event', 'admin.created')->count());
        $this->assertSame(3, AdminAuditLog::where('event', 'organization.access.updated')->count());
        $this->assertStringNotContainsString($created[0]['password'], json_encode(AdminAuditLog::all()->toArray())); // kata sandi tidak masuk riwayat

        // ulangi: tidak ada yang baru
        $this->assertSame([], $provisioner->provision($provisioner->plan(), null));
        $this->assertSame($before + 3, Admin::count());
    }

    public function test_provision_can_create_inactive_accounts(): void
    {
        $provisioner = app(UnitAdminProvisioner::class);
        $created = $provisioner->provision($provisioner->plan(['komda']), null, false);

        $this->assertCount(1, $created);
        $this->assertFalse(Admin::where('username', $created[0]['username'])->firstOrFail()->is_active);
    }

    // ---- halaman web -----------------------------------------------------

    public function test_only_super_admin_can_use_the_page(): void
    {
        $manager = $this->adminWithCreate();
        $this->actingAs($manager, 'admin')->get(route('admin.unit-admins.index'))->assertForbidden();
        $this->post(route('admin.unit-admins.store'), ['confirm' => '1'])->assertForbidden();
        $this->assertSame(0, AdminAuditLog::where('event', 'admin.created')->count());

        $nobody = $this->makeAdmin('tanpa-izin', null, null);
        $this->actingAs($nobody, 'admin')->get(route('admin.unit-admins.index'))->assertForbidden();
    }

    public function test_page_shows_plan_requires_confirmation_and_shows_passwords_once(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $this->get(route('admin.unit-admins.index'))->assertOk()
            ->assertSee('KOMWIL Sulawesi Tengah')->assertSee('Sudah punya akun')->assertSee('Unit nonaktif')->assertSee('sek-3-2')
            ->assertDontSee('Majelis Pendidikan');

        $before = Admin::count();
        $this->post(route('admin.unit-admins.store'), [])->assertSessionHasErrors('confirm');
        $this->assertSame($before, Admin::count());

        $response = $this->post(route('admin.unit-admins.store'), ['confirm' => '1', 'email_domain' => 'sekolah.sch.id']);
        $response->assertOk()->assertSee('hanya ditampilkan SEKALI')->assertSee('sek-3-2@sekolah.sch.id');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($before + 3, Admin::count());

        // kata sandi yang tampil memang cocok dengan akun yang dibuat
        $admin = Admin::where('username', 'komwil-sulteng')->firstOrFail();
        preg_match('/komwil-sulteng.*?<code class="user-select-all">([^<]+)<\/code>/s', $response->getContent(), $m);
        $this->assertNotEmpty($m[1] ?? null);
        $this->assertTrue(Hash::check($m[1], $admin->password));

        // dibuka lagi: tidak ada akun baru dan tidak ada kata sandi yang tampil
        $again = $this->post(route('admin.unit-admins.store'), ['confirm' => '1']);
        $again->assertOk()->assertSee('Tidak ada akun baru')->assertDontSee($m[1]);
        $this->assertSame($before + 3, Admin::count());
    }

    public function test_page_honours_type_filter_inactive_flag_and_validates_domain(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $this->post(route('admin.unit-admins.store'), ['confirm' => '1', 'email_domain' => 'bukan domain'])->assertSessionHasErrors('email_domain');
        $this->post(route('admin.unit-admins.store'), ['confirm' => '1', 'types' => ['hacker']])->assertSessionHasErrors('types.0');

        $this->post(route('admin.unit-admins.store'), ['confirm' => '1', 'types' => ['komda'], 'inactive' => '1'])->assertOk();
        $komda = Admin::whereHas('roles', fn ($q) => $q->where('roles.name', 'komisaris-daerah'))->firstOrFail();
        $this->assertFalse($komda->is_active);
        $this->assertSame(0, Admin::where('username', 'komwil-sulteng')->count()); // jenis lain tidak ikut dibuat
    }

    // ---- perintah CLI ----------------------------------------------------

    public function test_command_dry_run_and_invalid_types_create_nothing(): void
    {
        $before = Admin::count();

        $this->artisan('admin:provision-units', ['--dry-run' => true])->assertExitCode(0);
        $this->artisan('admin:provision-units', ['--types' => 'komda,ngawur', '--force' => true])->assertExitCode(1);

        $this->assertSame($before, Admin::count());
    }

    public function test_command_writes_a_private_csv_with_passwords_and_is_idempotent(): void
    {
        $files = fn () => glob(storage_path('app/private/provisioned-admins-*.csv')) ?: [];
        array_map('unlink', $files());

        try {
            $this->artisan('admin:provision-units', ['--types' => 'sekolah,komda', '--force' => true, '--inactive' => true])->assertExitCode(0);

            $this->assertCount(1, $files());
            $path = $files()[0];
            $this->assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
            $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
            $this->assertSame(['kode_unit', 'unit', 'jenis', 'peran', 'nama', 'username', 'email', 'password', 'status_akun'], $rows[0]);
            $this->assertCount(3, $rows); // header + KOMDA-PALU + SEK-3
            foreach (array_slice($rows, 1) as $r) {
                $admin = Admin::where('username', $r[5])->firstOrFail();
                $this->assertTrue(Hash::check($r[7], $admin->password));
                $this->assertFalse($admin->is_active);
                $this->assertSame('nonaktif', $r[8]);
            }

            $this->artisan('admin:provision-units', ['--types' => 'sekolah,komda', '--force' => true])
                ->expectsOutputToContain('Tidak ada akun baru')->assertExitCode(0);
            $this->assertCount(1, $files()); // tidak ada berkas baru
        } finally {
            array_map('unlink', $files());
        }
    }

    public function test_csv_cells_that_look_like_formulas_are_neutralised(): void
    {
        $this->assertSame("'=1+1", ProvisionUnitAdmins::safeCell('=1+1'));
        $this->assertSame("'@SUM(A1)", ProvisionUnitAdmins::safeCell('@SUM(A1)'));
        $this->assertSame("'-2", ProvisionUnitAdmins::safeCell('-2'));
        $this->assertSame('SD Tarbiyah 1', ProvisionUnitAdmins::safeCell('SD Tarbiyah 1'));
        $this->assertSame('', ProvisionUnitAdmins::safeCell(''));
    }
}
