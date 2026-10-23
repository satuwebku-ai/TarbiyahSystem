<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Permission;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Role;
use App\Models\UserUnitAccess;
use App\Models\WorkActivity;
use App\Models\WorkProgram;
use Database\Seeders\Demo\DemoProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_program_seeder_is_repeatable(): void
    {
        $this->seed(DemoProgramSeeder::class);
        $counts = fn () => [WorkProgram::count(), WorkActivity::count(), DB::table('activity_reports')->count(), DB::table('activity_approvals')->count()];
        $first = $counts();

        $this->seed(DemoProgramSeeder::class);

        $this->assertSame($first, $counts());
        $this->assertSame(7, $first[0]);
        $this->assertDatabaseHas('work_programs', ['code' => 'DEMO-PB-01', 'status' => 'approved']);
        foreach (['approved', 'submitted', 'draft', 'rejected'] as $status) {
            $this->assertTrue(WorkProgram::where('code', 'like', 'DEMO-KD-%')->where('status', $status)->exists(), $status);
        }
    }

    public function test_unit_restricted_admin_cannot_open_another_units_program(): void
    {
        $assignedUnit = $this->createUnit('unit-terbatas', 'UNIT-TERBATAS');
        $otherUnit = $this->createUnit('unit-lain', 'UNIT-LAIN');
        $program = WorkProgram::query()->create([
            'organizational_unit_id' => $otherUnit->id,
            'code' => 'PRIVATE-01',
            'title' => 'Program Unit Lain',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
            'estimated_budget' => 100000,
            'status' => 'draft',
        ]);

        $permission = Permission::query()->create([
            'name' => 'programs.view',
            'label' => 'Lihat program',
            'group' => 'Program Kerja & Kegiatan',
            'guard_name' => 'admin',
        ]);
        $role = Role::query()->create([
            'name' => 'pengelola-unit',
            'label' => 'Pengelola Unit',
            'guard_name' => 'admin',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);
        $admin = Admin::query()->create([
            'name' => 'Admin Terbatas',
            'username' => 'admin-terbatas',
            'email' => 'admin-terbatas@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);
        UserUnitAccess::query()->create([
            'organizational_unit_id' => $assignedUnit->id,
            'model_type' => Admin::class,
            'model_id' => $admin->id,
            'created_at' => now(),
        ]);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.programs.index'))
            ->assertOk()
            ->assertDontSee('Program Unit Lain');
        $this->get(route('admin.programs.show', $program))->assertForbidden();
    }

    public function test_super_admin_can_plan_approve_and_report_a_work_program(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin, 'admin');
        $unit = $this->createUnit();

        $this->post(route('admin.programs.store'), [
            'organizational_unit_id' => $unit->id,
            'code' => 'PRG-TEST-01',
            'title' => 'Program Literasi',
            'description' => 'Program untuk pengujian alur fase enam.',
            'objectives' => 'Meningkatkan literasi digital peserta.',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
            'estimated_budget' => 3000000,
        ])->assertSessionHasNoErrors();

        $program = WorkProgram::query()->where('code', 'PRG-TEST-01')->firstOrFail();
        $this->post(route('admin.programs.submit', $program))->assertSessionHasNoErrors();
        $this->patch(route('admin.programs.review', $program), ['decision' => 'approved'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_programs', ['id' => $program->id, 'status' => 'approved']);

        $this->post(route('admin.programs.activities.store', $program), [
            'code' => 'ACT-TEST-01',
            'title' => 'Lokakarya Contoh',
            'description' => 'Kegiatan untuk uji laporan.',
            'planned_start_at' => today()->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'),
            'planned_end_at' => today()->addDays(2)->setTime(12, 0)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $activity = WorkActivity::query()->where('code', 'ACT-TEST-01')->firstOrFail();
        $this->post(route('admin.programs.activities.budget-items.store', [$program, $activity]), [
            'name' => 'Konsumsi',
            'planned_amount' => 500000,
            'realized_amount' => 450000,
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.programs.activities.participants.store', [$program, $activity]), [
            'participant_name' => 'Peserta Contoh',
            'role' => 'Peserta',
        ])->assertSessionHasNoErrors();

        $this->patch(route('admin.programs.activities.status', [$program, $activity]), ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.programs.activities.report.save', [$program, $activity]), [
            'summary' => 'Kegiatan dilaksanakan sesuai rencana dan jadwal.',
            'outcomes' => 'Peserta mengikuti seluruh materi.',
            'challenges' => '',
            'recommendations' => 'Ulangi kegiatan pada semester berikutnya.',
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.programs.activities.report.submit', [$program, $activity]))
            ->assertSessionHasNoErrors();
        $this->patch(route('admin.programs.activities.report.review', [$program, $activity]), [
            'decision' => 'reviewed',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_reports', [
            'work_activity_id' => $activity->id,
            'status' => 'reviewed',
        ]);
        $this->assertDatabaseHas('activity_budget_items', [
            'work_activity_id' => $activity->id,
            'planned_amount' => 500000,
            'realized_amount' => 450000,
        ]);

        $this->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('Program Literasi');
        $this->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('Konsumsi')
            ->assertSee('Peserta Contoh')
            ->assertSee('Kegiatan untuk uji laporan.');
    }

    private function createUnit(string $slug = 'unit-pengujian', string $code = 'UNIT-TEST'): OrganizationalUnit
    {
        $type = OrganizationalUnitType::query()->create([
            'name' => 'Unit Pengujian',
            'slug' => $slug,
            'is_active' => true,
        ]);

        return OrganizationalUnit::query()->create([
            'type_id' => $type->id,
            'code' => $code,
            'name' => 'Unit Pengujian',
            'status' => 'active',
        ]);
    }

    private function createSuperAdmin(): Admin
    {
        $role = Role::query()->create([
            'name' => 'super-admin',
            'label' => 'Super Admin',
            'guard_name' => 'admin',
            'description' => 'Akses penuh untuk pengujian.',
            'is_system' => true,
        ]);
        $admin = Admin::query()->create([
            'name' => 'Admin Fase Enam',
            'username' => 'admin-fase-enam',
            'email' => 'admin-fase-enam@example.test',
            'password' => 'Secure-Test-Password-2026!',
            'is_active' => true,
        ]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }
}
