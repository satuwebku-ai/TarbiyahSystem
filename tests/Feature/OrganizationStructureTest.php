<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_view_organization_structure(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.organization.index'))
            ->assertOk()
            ->assertSee('Bagan unit organisasi');

        $this->post(route('admin.organization.unit-types.store'), [
            'name' => 'Yayasan',
            'slug' => 'yayasan',
            'description' => 'Unit induk organisasi',
            'is_active' => 1,
        ])->assertRedirect(route('admin.organization.unit-types.index'));

        $type = OrganizationalUnitType::query()->firstOrFail();

        $this->post(route('admin.organization.units.store'), [
            'type_id' => $type->id,
            'parent_id' => null,
            'code' => 'YYS-001',
            'name' => 'Yayasan Contoh',
            'description' => 'Unit utama',
            'address' => 'Alamat contoh',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $unit = OrganizationalUnit::query()->firstOrFail();

        $this->post(route('admin.organization.positions.store'), [
            'organizational_unit_id' => $unit->id,
            'name' => 'Ketua',
            'description' => 'Pimpinan unit',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $positionId = $unit->positions()->value('id');

        $member = $this->createEmployee('Anggota Contoh', $unit);

        $this->post(route('admin.organization.assignments.store'), [
            'organizational_unit_id' => $unit->id,
            'organizational_position_id' => $positionId,
            'admin_id' => null,
            'employee_id' => $member->id,
            'start_date' => today()->toDateString(),
            'end_date' => null,
        ])->assertSessionHasNoErrors();

        $this->get(route('admin.organization.units.show', $unit))
            ->assertOk()
            ->assertSee('Yayasan Contoh')
            ->assertSee('Ketua')
            ->assertSee('Anggota Contoh');

        $this->assertDatabaseHas('organizational_unit_histories', [
            'organizational_unit_id' => $unit->id,
            'action' => 'created',
        ]);
    }

    public function test_only_one_pengurus_besar_is_allowed(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $types = $this->createLevelTypes();

        $this->post(route('admin.organization.units.store'), $this->payload($types['pengurus-besar'], null, 'PB-1', 'Pengurus Besar'))
            ->assertSessionHasNoErrors();

        $this->post(route('admin.organization.units.store'), $this->payload($types['pengurus-besar'], null, 'PB-2', 'Pengurus Besar Kedua'))
            ->assertSessionHasErrors('type_id');

        $this->assertSame(1, OrganizationalUnit::query()->where('type_id', $types['pengurus-besar']->id)->count());
    }

    public function test_hierarchy_levels_are_enforced(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $types = $this->createLevelTypes();

        $this->post(route('admin.organization.units.store'), $this->payload($types['pengurus-besar'], null, 'PB-1', 'Pengurus Besar'));
        $pb = OrganizationalUnit::query()->where('code', 'PB-1')->firstOrFail();

        // KOMDA langsung di bawah Pengurus Besar ditolak.
        $this->post(route('admin.organization.units.store'), $this->payload($types['komda'], $pb->id, 'KOMDA-X', 'KOMDA X'))
            ->assertSessionHasErrors('parent_id');

        // Pengurus Besar tidak boleh punya induk.
        $this->post(route('admin.organization.units.store'), $this->payload($types['pengurus-besar'], $pb->id, 'PB-2', 'PB Anak'))
            ->assertSessionHasErrors('parent_id');

        // Rantai yang benar diterima: PB -> KOMWIL -> KOMDA -> Sekolah.
        $this->post(route('admin.organization.units.store'), $this->payload($types['komwil'], $pb->id, 'KOMWIL-A', 'KOMWIL A'))
            ->assertSessionHasNoErrors();
        $komwil = OrganizationalUnit::query()->where('code', 'KOMWIL-A')->firstOrFail();

        $this->post(route('admin.organization.units.store'), $this->payload($types['komda'], $komwil->id, 'KOMDA-A', 'KOMDA A'))
            ->assertSessionHasNoErrors();
        $komda = OrganizationalUnit::query()->where('code', 'KOMDA-A')->firstOrFail();

        $this->post(route('admin.organization.units.store'), $this->payload($types['sekolah'], $komda->id, 'SD-A', 'SD A'))
            ->assertSessionHasNoErrors();

        // Sekolah tidak boleh menjadi induk unit lain.
        $school = OrganizationalUnit::query()->where('code', 'SD-A')->firstOrFail();
        $this->post(route('admin.organization.units.store'), $this->payload($types['komda'], $school->id, 'KOMDA-B', 'KOMDA B'))
            ->assertSessionHasErrors('parent_id');
    }

    public function test_unit_on_a_broken_chain_cannot_become_a_parent(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $types = $this->createLevelTypes();

        // Data lama yang melanggar aturan: Pengurus Besar berada di bawah KOMWIL.
        $komwil = OrganizationalUnit::query()->create(['type_id' => $types['komwil']->id, 'parent_id' => null, 'code' => 'KW', 'name' => 'KW', 'status' => 'active']);
        $legacyPb = OrganizationalUnit::query()->create(['type_id' => $types['pengurus-besar']->id, 'parent_id' => $komwil->id, 'code' => 'PB-L', 'name' => 'PB Lama', 'status' => 'active']);

        $this->post(route('admin.organization.units.store'), $this->payload($types['komwil'], $legacyPb->id, 'KW-2', 'KW 2'))
            ->assertSessionHasErrors('parent_id');
    }

    public function test_tree_modal_creates_child_and_returns_to_organization_chart(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $types = $this->createLevelTypes();
        $pb = OrganizationalUnit::query()->create(['type_id' => $types['pengurus-besar']->id, 'code' => 'PB-1', 'name' => 'Pengurus Besar', 'status' => 'active']);

        $this->get(route('admin.organization.index'))
            ->assertOk()
            ->assertSee('data-parent-id="'.$pb->id.'"', false);

        $this->post(route('admin.organization.units.store'), $this->payload($types['komwil'], $pb->id, 'KOMWIL-A', 'KOMWIL A') + [
            'from_modal' => 1,
            'return_to' => 'organization',
        ])->assertRedirect(route('admin.organization.index'));

        $this->assertDatabaseHas('organizational_units', ['code' => 'KOMWIL-A', 'parent_id' => $pb->id]);
    }

    public function test_units_page_shows_tree_with_ancestors_of_filtered_units_and_table_fallback(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $types = $this->createLevelTypes();
        $pb = OrganizationalUnit::query()->create(['type_id' => $types['pengurus-besar']->id, 'code' => 'PB-1', 'name' => 'Pengurus Besar Induk', 'status' => 'active']);
        $komwil = OrganizationalUnit::query()->create(['type_id' => $types['komwil']->id, 'parent_id' => $pb->id, 'code' => 'KW-A', 'name' => 'KOMWIL Alpha', 'status' => 'active']);
        OrganizationalUnit::query()->create(['type_id' => $types['komwil']->id, 'parent_id' => $pb->id, 'code' => 'KW-B', 'name' => 'KOMWIL Beta', 'status' => 'active']);
        OrganizationalUnit::query()->create(['type_id' => $types['komda']->id, 'parent_id' => $komwil->id, 'code' => 'KD-A', 'name' => 'KOMDA Anggrek', 'status' => 'active']);

        // Bagan adalah tampilan bawaan dan memuat tombol tambah anak.
        $this->get(route('admin.organization.units.index'))
            ->assertOk()
            ->assertSee('id="orgTree"', false)
            ->assertSee('data-parent-id="'.$komwil->id.'"', false);

        // Filter menampilkan unit yang cocok beserta induknya, tanpa saudara yang tidak cocok.
        $this->get(route('admin.organization.units.index', ['search' => 'Anggrek']))
            ->assertOk()
            ->assertSee('KOMDA Anggrek')
            ->assertSee('KOMWIL Alpha')
            ->assertSee('Pengurus Besar Induk')
            ->assertDontSee('KOMWIL Beta');

        // Tampilan tabel tetap tersedia.
        $this->get(route('admin.organization.units.index', ['view' => 'table']))
            ->assertOk()
            ->assertDontSee('id="orgTree"', false)
            ->assertSee('KOMWIL Beta');
    }

    public function test_positions_page_is_a_tree_scoped_to_the_logged_in_admins_units(): void
    {
        $types = $this->createLevelTypes();
        $pb = OrganizationalUnit::query()->create(['type_id' => $types['pengurus-besar']->id, 'code' => 'PB-1', 'name' => 'Pengurus Besar Induk', 'status' => 'active']);
        $komwil = OrganizationalUnit::query()->create(['type_id' => $types['komwil']->id, 'parent_id' => $pb->id, 'code' => 'KW-A', 'name' => 'KOMWIL Alpha', 'status' => 'active']);
        $komda = OrganizationalUnit::query()->create(['type_id' => $types['komda']->id, 'parent_id' => $komwil->id, 'code' => 'KD-A', 'name' => 'KOMDA Anggrek', 'status' => 'active']);
        $other = OrganizationalUnit::query()->create(['type_id' => $types['komwil']->id, 'parent_id' => $pb->id, 'code' => 'KW-B', 'name' => 'KOMWIL Beta', 'status' => 'active']);
        \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $pb->id, 'name' => 'Ketua Umum', 'is_active' => true]);
        \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $komwil->id, 'name' => 'Komisaris Wilayah', 'is_active' => true]);
        \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $komda->id, 'name' => 'Komisaris Daerah', 'is_active' => true]);
        \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $other->id, 'name' => 'Komisaris Beta', 'is_active' => true]);

        // Super admin: bagan dimulai dari Pengurus Besar dan memuat semua jabatan.
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $this->get(route('admin.organization.positions.index'))
            ->assertOk()
            ->assertSee('id="orgTree"', false)
            ->assertSee('Ketua Umum')
            ->assertSee('Komisaris Beta');

        // Pencarian menampilkan jabatan yang cocok beserta unit induknya.
        $this->get(route('admin.organization.positions.index', ['search' => 'Komisaris Daerah']))
            ->assertOk()
            ->assertSee('KOMDA Anggrek')
            ->assertSee('KOMWIL Alpha')
            ->assertDontSee('Komisaris Beta');

        // Tampilan tabel tetap tersedia.
        $this->get(route('admin.organization.positions.index', ['view' => 'table']))
            ->assertOk()
            ->assertDontSee('id="orgTree"', false);
    }

    public function test_assignment_uses_employee_name_and_account_scoped_to_the_unit(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        [$pb, $alpha, $beta] = $this->createRegions();
        $position = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $alpha->id, 'name' => 'Komisaris Wilayah', 'is_active' => true]);
        $inAlpha = $this->createEmployee('Pegawai Alpha', $alpha);
        $inBeta = $this->createEmployee('Pegawai Beta', $beta);
        $accountAlpha = $this->createAccessAccount('akun.alpha', 'Akun Alpha', $alpha);
        $accountBeta = $this->createAccessAccount('akun.beta', 'Akun Beta', $beta);

        // Form memuat pilihan yang diberi daftar unit agar bisa disaring per unit; tidak ada lagi input nama manual.
        $this->get(route('admin.organization.assignments.create'))
            ->assertOk()
            ->assertSee('Pegawai Alpha')
            ->assertSee('akun.alpha')
            ->assertSee('name="employee_id"', false)
            ->assertDontSee('name="assignee_name"', false)
            ->assertSee('Pengurus Besar › KOMWIL Alpha');

        $base = ['organizational_unit_id' => $alpha->id, 'organizational_position_id' => $position->id, 'start_date' => today()->toDateString()];

        // Pegawai dari daerah lain ditolak.
        $this->post(route('admin.organization.assignments.store'), $base + ['employee_id' => $inBeta->id])
            ->assertSessionHasErrors('employee_id');
        // Akun dari daerah lain ditolak.
        $this->post(route('admin.organization.assignments.store'), $base + ['employee_id' => $inAlpha->id, 'admin_id' => $accountBeta->id])
            ->assertSessionHasErrors('admin_id');
        $this->assertDatabaseCount('organizational_assignments', 0);

        // Nama mengikuti data pegawai; input nama manual diabaikan.
        $this->post(route('admin.organization.assignments.store'), $base + [
            'employee_id' => $inAlpha->id, 'admin_id' => $accountAlpha->id, 'assignee_name' => 'diabaikan',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organizational_assignments', [
            'employee_id' => $inAlpha->id, 'admin_id' => $accountAlpha->id, 'assignee_name' => 'Pegawai Alpha',
        ]);
    }

    public function test_assignments_page_is_a_tree_with_table_fallback(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        [$pb, $alpha, $beta] = $this->createRegions();
        $position = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $alpha->id, 'name' => 'Komisaris Wilayah', 'is_active' => true]);
        $other = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $beta->id, 'name' => 'Komisaris Beta', 'is_active' => true]);
        $employee = $this->createEmployee('Pegawai Alpha', $alpha);
        $stranger = $this->createEmployee('Pegawai Beta', $beta);
        foreach ([[$alpha, $position, $employee], [$beta, $other, $stranger]] as [$unit, $pos, $person]) {
            \App\Models\OrganizationalAssignment::query()->create([
                'organizational_unit_id' => $unit->id, 'organizational_position_id' => $pos->id, 'employee_id' => $person->id,
                'assignee_name' => $person->name, 'start_date' => '2026-01-01', 'status' => 'active',
            ]);
        }

        $this->get(route('admin.organization.assignments.index'))
            ->assertOk()
            ->assertSee('id="orgTree"', false)
            ->assertSee('Pengurus Besar')
            ->assertSee('KOMWIL Alpha')
            ->assertSee('Pegawai Alpha')
            ->assertSee('Pegawai Beta');

        // Memilih unit membatasi bagan ke unit itu beserta turunannya (induk tidak ikut).
        $this->get(route('admin.organization.assignments.index', ['unit_id' => $alpha->id]))
            ->assertOk()
            ->assertSee('Pegawai Alpha')
            ->assertDontSee('Pegawai Beta');

        // Pencarian menampilkan penugasan yang cocok beserta unit induknya.
        $this->get(route('admin.organization.assignments.index', ['search' => 'Komisaris Beta']))
            ->assertOk()
            ->assertSee('Pegawai Beta')
            ->assertSee('Pengurus Besar')
            ->assertDontSee('Pegawai Alpha');

        $this->get(route('admin.organization.assignments.index', ['view' => 'table']))
            ->assertOk()
            ->assertDontSee('id="orgTree"', false)
            ->assertSee('Pegawai Alpha');
    }

    public function test_assignments_tree_is_scoped_to_the_logged_in_admins_units(): void
    {
        [$pb, $alpha, $beta] = $this->createRegions();
        $posA = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $alpha->id, 'name' => 'Posisi A', 'is_active' => true]);
        $posB = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $beta->id, 'name' => 'Posisi B', 'is_active' => true]);
        foreach ([[$alpha, $posA, 'Orang Alpha'], [$beta, $posB, 'Orang Beta']] as [$unit, $pos, $name]) {
            \App\Models\OrganizationalAssignment::query()->create([
                'organizational_unit_id' => $unit->id, 'organizational_position_id' => $pos->id,
                'assignee_name' => $name, 'start_date' => '2026-01-01', 'status' => 'active',
            ]);
        }

        $scoped = $this->createAccessAccount('komwil.alpha', 'Pengelola Alpha', $alpha, ['organization.view', 'organization.assignments.manage']);
        $this->actingAs($scoped, 'admin');

        $this->get(route('admin.organization.assignments.index'))
            ->assertOk()
            ->assertSee('Orang Alpha')
            ->assertDontSee('Orang Beta');

        // Form hanya menawarkan unit dalam cakupan dan menolak unit lain di server.
        $this->get(route('admin.organization.assignments.create'))->assertOk()->assertDontSee('KOMWIL Beta');
        $employee = $this->createEmployee('Pegawai Beta', $beta);
        $this->post(route('admin.organization.assignments.store'), [
            'organizational_unit_id' => $beta->id, 'organizational_position_id' => $posB->id,
            'employee_id' => $employee->id, 'start_date' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_an_employee_cannot_hold_two_positions_at_the_same_time(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        [$pb, $alpha] = $this->createRegions();
        $chair = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $alpha->id, 'name' => 'Komisaris Wilayah', 'is_active' => true]);
        $secretary = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $alpha->id, 'name' => 'Sekretaris Wilayah', 'is_active' => true]);
        $person = $this->createEmployee('Pegawai Rangkap', $alpha);
        $payload = fn ($position, array $extra = []) => array_merge([
            'organizational_unit_id' => $alpha->id, 'organizational_position_id' => $position->id,
            'employee_id' => $person->id, 'start_date' => '2026-01-01',
        ], $extra);

        $this->post(route('admin.organization.assignments.store'), $payload($chair))->assertSessionHasNoErrors();

        // Sudah menjabat: tidak muncul di dropdown dan ditolak di server.
        $this->get(route('admin.organization.assignments.create'))->assertOk()->assertDontSee('Pegawai Rangkap');
        $this->post(route('admin.organization.assignments.store'), $payload($secretary))->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('organizational_assignments', 1);

        // Setelah penugasan pertama berakhir, ia boleh menjabat lagi mulai tanggal berikutnya.
        $first = \App\Models\OrganizationalAssignment::firstOrFail();
        $this->patch(route('admin.organization.assignments.end', $first), ['end_date' => '2026-06-30'])->assertSessionHasNoErrors();
        $this->post(route('admin.organization.assignments.store'), $payload($secretary, ['start_date' => '2026-07-01']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('organizational_assignments', 2);

        // Mengubah penugasan sendiri tidak dianggap bentrok dengan dirinya.
        $second = \App\Models\OrganizationalAssignment::where('organizational_position_id', $secretary->id)->firstOrFail();
        $this->put(route('admin.organization.assignments.update', $second), $payload($secretary, ['start_date' => '2026-07-01']))->assertSessionHasNoErrors();
    }

    public function test_assignment_on_a_deactivated_position_can_still_be_edited(): void
    {
        $this->actingAs($this->createSuperAdmin(), 'admin');
        $types = $this->createLevelTypes();
        $pb = OrganizationalUnit::query()->create(['type_id' => $types['pengurus-besar']->id, 'code' => 'PB-1', 'name' => 'Pengurus Besar', 'status' => 'active']);
        $position = \App\Models\OrganizationalPosition::query()->create(['organizational_unit_id' => $pb->id, 'name' => 'Sekretaris Umum', 'is_active' => true]);
        $assignment = \App\Models\OrganizationalAssignment::query()->create([
            'organizational_unit_id' => $pb->id,
            'organizational_position_id' => $position->id,
            'assignee_name' => 'Nama Lama',
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);
        $position->update(['is_active' => false]);
        $employee = $this->createEmployee('Nama Baru', $pb);

        // Penugasan lama bernama manual tetap bisa dibuka dan diberi peringatan agar dihubungkan ke pegawai.
        $this->get(route('admin.organization.assignments.edit', $assignment))
            ->assertOk()
            ->assertSee('Sekretaris Umum')
            ->assertSee('Nama Lama');

        // Tanpa memilih pegawai, simpan ditolak.
        $this->put(route('admin.organization.assignments.update', $assignment), [
            'organizational_unit_id' => $pb->id,
            'organizational_position_id' => $position->id,
            'start_date' => '2026-01-01',
        ])->assertSessionHasErrors('employee_id');

        $this->put(route('admin.organization.assignments.update', $assignment), [
            'organizational_unit_id' => $pb->id,
            'organizational_position_id' => $position->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organizational_assignments', ['id' => $assignment->id, 'employee_id' => $employee->id, 'assignee_name' => 'Nama Baru', 'status' => 'active']);
    }

    private function payload(OrganizationalUnitType $type, ?int $parentId, string $code, string $name): array
    {
        return [
            'type_id' => $type->id,
            'parent_id' => $parentId,
            'code' => $code,
            'name' => $name,
            'status' => 'active',
        ];
    }

    /** @return array<string, OrganizationalUnitType> */
    /** @return array{0: OrganizationalUnit, 1: OrganizationalUnit, 2: OrganizationalUnit} */
    private function createRegions(): array
    {
        $types = $this->createLevelTypes();
        $pb = OrganizationalUnit::query()->create(['type_id' => $types['pengurus-besar']->id, 'code' => 'PB-1', 'name' => 'Pengurus Besar', 'status' => 'active']);
        $alpha = OrganizationalUnit::query()->create(['type_id' => $types['komwil']->id, 'parent_id' => $pb->id, 'code' => 'KW-A', 'name' => 'KOMWIL Alpha', 'status' => 'active']);
        $beta = OrganizationalUnit::query()->create(['type_id' => $types['komwil']->id, 'parent_id' => $pb->id, 'code' => 'KW-B', 'name' => 'KOMWIL Beta', 'status' => 'active']);

        return [$pb, $alpha, $beta];
    }

    private function createEmployee(string $name, OrganizationalUnit $unit): \App\Models\Employee
    {
        static $n = 0;
        $employee = \App\Models\Employee::query()->create([
            'employee_number' => 'PEG-T'.(++$n), 'name' => $name, 'employee_type' => 'staff', 'status' => 'active',
        ]);
        $employee->assignments()->create(['organizational_unit_id' => $unit->id, 'start_date' => '2026-01-01']);

        return $employee;
    }

    /** Akun panel yang diberi Akses Unit pada satu unit; izin tambahan opsional (membuat peran terbatas). */
    private function createAccessAccount(string $username, string $name, OrganizationalUnit $unit, array $permissions = []): Admin
    {
        $account = Admin::query()->create(['name' => $name, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        \App\Models\UserUnitAccess::query()->create(['organizational_unit_id' => $unit->id, 'model_type' => Admin::class, 'model_id' => $account->id, 'created_at' => now()]);

        if ($permissions) {
            $role = Role::query()->create(['name' => 'role-'.$username, 'label' => $name, 'guard_name' => 'admin', 'is_system' => false]);
            foreach ($permissions as $permission) {
                $row = \App\Models\Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'admin'], ['label' => $permission]);
                $role->permissions()->syncWithoutDetaching([$row->id]);
            }
            $account->syncRoles([$role->id]);
        }

        return $account;
    }

    private function createLevelTypes(): array
    {
        $types = [];
        foreach (['pengurus-besar' => 'Pengurus Besar', 'komwil' => 'KOMWIL', 'komda' => 'KOMDA', 'sekolah' => 'Sekolah'] as $slug => $name) {
            $types[$slug] = OrganizationalUnitType::query()->create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
        }

        return $types;
    }

    private function createSuperAdmin(): Admin
    {
        $role = Role::query()->create([
            'name' => 'super-admin',
            'label' => 'Super Admin',
            'guard_name' => 'admin',
            'description' => 'Akses penuh',
            'is_system' => true,
        ]);

        $admin = Admin::query()->create([
            'name' => 'Admin Pengujian',
            'username' => 'admin-test',
            'email' => 'admin-test@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }
}
