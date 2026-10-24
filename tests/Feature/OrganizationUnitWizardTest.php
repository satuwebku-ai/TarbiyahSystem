<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Form wizard unit organisasi: halaman, cek kode, dan validasi server berbahasa Indonesia. */
class OrganizationUnitWizardTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnitType $pb;

    private OrganizationalUnitType $komwil;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::query()->create(['name' => 'super-admin', 'label' => 'Super Admin', 'guard_name' => 'admin', 'is_system' => true]);
        $admin = Admin::query()->create(['name' => 'SA', 'username' => 'sa-wz', 'email' => 'sa-wz@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);
        $this->actingAs($admin, 'admin');

        $this->pb = OrganizationalUnitType::query()->create(['name' => 'Pengurus Besar', 'slug' => 'pengurus-besar', 'is_active' => true]);
        $this->komwil = OrganizationalUnitType::query()->create(['name' => 'KOMWIL', 'slug' => 'komwil', 'is_active' => true]);
    }

    private function makePb(string $code = 'PB-1'): OrganizationalUnit
    {
        return OrganizationalUnit::query()->create(['type_id' => $this->pb->id, 'code' => $code, 'name' => 'Pengurus Besar', 'status' => 'active']);
    }

    public function test_create_page_renders_the_wizard_steps(): void
    {
        $this->get(route('admin.organization.units.create'))
            ->assertOk()
            ->assertSee('wzSteps', false)
            ->assertSee('Pilih jenis unit')
            ->assertSee('Unit induk')
            ->assertSee('Identitas')
            ->assertSee('Detail');
    }

    public function test_edit_page_renders_the_wizard(): void
    {
        $unit = $this->makePb();

        $this->get(route('admin.organization.units.edit', $unit))->assertOk()->assertSee('wzSteps', false);
    }

    public function test_check_code_reports_availability_and_ignores_own_unit(): void
    {
        $unit = $this->makePb('PB-1');

        $this->getJson(route('admin.organization.units.check-code', ['code' => 'PB-1']))->assertOk()->assertJson(['available' => false]);
        $this->getJson(route('admin.organization.units.check-code', ['code' => 'PB-9']))->assertOk()->assertJson(['available' => true]);
        $this->getJson(route('admin.organization.units.check-code', ['code' => 'PB-1', 'ignore' => $unit->id]))->assertOk()->assertJson(['available' => true]);
    }

    public function test_check_code_requires_a_code(): void
    {
        $this->getJson(route('admin.organization.units.check-code'))->assertStatus(422);
    }

    public function test_store_rejects_invalid_code_format_with_indonesian_message(): void
    {
        $this->post(route('admin.organization.units.store'), [
            'type_id' => $this->pb->id, 'code' => 'kode salah!', 'name' => 'Pengurus Besar', 'status' => 'active',
        ])->assertSessionHasErrors(['code' => 'Kode hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung (diawali huruf/angka), tanpa spasi.']);

        $this->assertSame(0, OrganizationalUnit::query()->count());
    }

    public function test_store_rejects_duplicate_code_with_clear_message(): void
    {
        $this->makePb('PB-1');
        $pb = $this->makePb('PB-0');

        $this->post(route('admin.organization.units.store'), [
            'type_id' => $this->komwil->id, 'parent_id' => $pb->id, 'code' => 'PB-1', 'name' => 'KOMWIL A', 'status' => 'active',
        ])->assertSessionHasErrors(['code' => 'Kode "PB-1" sudah dipakai unit lain. Gunakan kode yang berbeda.']);
    }

    public function test_store_requires_name_and_type_with_indonesian_messages(): void
    {
        $this->post(route('admin.organization.units.store'), ['code' => 'X-1', 'status' => 'active'])
            ->assertSessionHasErrors([
                'type_id' => 'Pilih jenis unit terlebih dahulu.',
                'name' => 'Nama unit wajib diisi.',
            ]);
    }

    public function test_legacy_code_with_old_format_can_still_be_saved_when_unchanged(): void
    {
        $unit = OrganizationalUnit::query()->create(['type_id' => $this->pb->id, 'code' => 'kode lama', 'name' => 'Pengurus Besar', 'status' => 'active']);

        $this->put(route('admin.organization.units.update', $unit), [
            'type_id' => $this->pb->id, 'code' => 'kode lama', 'name' => 'Pengurus Besar Baru', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Pengurus Besar Baru', $unit->fresh()->name);
    }

    public function test_valid_submission_still_creates_the_unit(): void
    {
        $pb = $this->makePb();

        $this->post(route('admin.organization.units.store'), [
            'type_id' => $this->komwil->id, 'parent_id' => $pb->id, 'code' => 'KOMWIL-SULSEL', 'name' => 'KOMWIL Sulawesi Selatan', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organizational_units', ['code' => 'KOMWIL-SULSEL', 'parent_id' => $pb->id]);
    }

    public function test_units_index_has_add_unit_wizard_modal_with_parent_choices(): void
    {
        $pb = $this->makePb('PB-1');

        $this->get(route('admin.organization.units.index'))
            ->assertOk()
            ->assertSee('id="orgAddModal"', false)
            ->assertSee('id="orgAddSteps"', false)
            ->assertSee('data-bs-target="#orgAddModal"', false)
            ->assertSee('value="'.$pb->id.'"', false)
            ->assertSee('Unit induk');

        // Tampilan tabel memakai wizard yang sama.
        $this->get(route('admin.organization.units.index', ['view' => 'table']))
            ->assertOk()
            ->assertSee('id="orgAddModal"', false);
    }

    public function test_wizard_from_units_index_returns_to_units_index(): void
    {
        $pb = $this->makePb('PB-1');

        $this->post(route('admin.organization.units.store'), [
            'from_modal' => 1, 'return_to' => 'units',
            'type_id' => $this->komwil->id, 'parent_id' => $pb->id, 'code' => 'KOMWIL-A', 'name' => 'KOMWIL A', 'status' => 'active',
        ])->assertRedirect(route('admin.organization.units.index'));

        $this->assertDatabaseHas('organizational_units', ['code' => 'KOMWIL-A', 'parent_id' => $pb->id]);
    }

    public function test_wizard_rejects_type_not_allowed_under_chosen_parent(): void
    {
        $pb = $this->makePb('PB-1');

        $this->post(route('admin.organization.units.store'), [
            'from_modal' => 1, 'return_to' => 'units',
            'type_id' => $this->pb->id, 'parent_id' => $pb->id, 'code' => 'PB-2', 'name' => 'PB Kedua', 'status' => 'active',
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_wizard_warns_and_links_to_unit_types_when_no_active_type_exists(): void
    {
        OrganizationalUnitType::query()->update(['is_active' => false]);

        $this->get(route('admin.organization.units.index'))
            ->assertOk()
            ->assertSee('id="orgAddNoParent"', false)
            ->assertSee('Belum ada jenis unit aktif')
            ->assertSee(route('admin.organization.unit-types.create'), false);
    }

    public function test_wizard_warns_when_no_parent_unit_can_take_children(): void
    {
        OrganizationalUnit::query()->create(['type_id' => $this->pb->id, 'code' => 'PB-1', 'name' => 'Pengurus Besar', 'status' => 'inactive']);

        $this->get(route('admin.organization.units.index'))
            ->assertOk()
            ->assertSee('id="orgAddNoParent"', false)
            ->assertSee('Belum ada unit induk yang bisa diberi turunan');
    }

    public function test_wizard_has_no_warning_when_a_parent_exists(): void
    {
        $this->makePb('PB-1');

        $this->get(route('admin.organization.units.index'))
            ->assertOk()
            ->assertDontSee('id="orgAddNoParent"', false);
    }

    public function test_units_index_shows_input_flow_with_counts_between_filter_and_tree(): void
    {
        $komda = OrganizationalUnitType::query()->create(['name' => 'KOMDA', 'slug' => 'komda', 'is_active' => true]);
        $pb = $this->makePb('PB-1');
        OrganizationalUnit::query()->create(['type_id' => $this->komwil->id, 'parent_id' => $pb->id, 'code' => 'KW-A', 'name' => 'KOMWIL A', 'status' => 'active']);
        OrganizationalUnit::query()->create(['type_id' => $this->komwil->id, 'parent_id' => $pb->id, 'code' => 'KW-B', 'name' => 'KOMWIL B', 'status' => 'active']);

        $response = $this->get(route('admin.organization.units.index'))
            ->assertOk()
            ->assertSee('id="orgUnitFlow"', false)
            ->assertSee('Alur pengisian unit organisasi')
            ->assertSee('data-type-id="'.$this->komwil->id.'"', false)
            ->assertSee('data-type-id="'.$komda->id.'"', false);

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'id="orgTree"'), strpos($html, 'id="orgUnitFlow"'));
        $this->assertGreaterThan(strpos($html, 'id="unitFilter"'), strpos($html, 'id="orgUnitFlow"'));
        $this->assertMatchesRegularExpression('/uf-title">\s*KOMWIL\s*<\/div>.*?uf-num">2</s', $html);
    }

    public function test_input_flow_locks_levels_whose_parent_level_is_empty(): void
    {
        OrganizationalUnitType::query()->create(['name' => 'KOMDA', 'slug' => 'komda', 'is_active' => true]);
        $this->makePb('PB-1');

        $this->get(route('admin.organization.units.index'))
            ->assertOk()
            ->assertSee('Isi KOMWIL terlebih dahulu.')
            ->assertSee('Langkah berikutnya.');
    }
}
