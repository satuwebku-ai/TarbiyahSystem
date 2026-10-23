<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\YayasanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class YayasanSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YayasanSeeder::class);
    }

    public function test_hierarchy_is_pb_then_komwil_then_komda_then_three_schools_each(): void
    {
        $pb = OrganizationalUnit::where('code', 'PB-YAYASAN')->firstOrFail();
        $this->assertNull($pb->parent_id);

        $komwils = OrganizationalUnit::whereHas('type', fn ($q) => $q->where('slug', 'komwil'))->get();
        $this->assertCount(3, $komwils);
        $komwils->each(fn ($u) => $this->assertSame($pb->id, $u->parent_id));

        $komdas = OrganizationalUnit::whereHas('type', fn ($q) => $q->where('slug', 'komda'))->get();
        $this->assertCount(7, $komdas);
        foreach ($komdas as $komda) {
            $this->assertContains($komda->parent_id, $komwils->pluck('id'));
            $levels = School::whereHas('unit', fn ($q) => $q->where('parent_id', $komda->id))->with('level')->get()->pluck('level.slug')->sort()->values()->all();
            $this->assertSame(['sd', 'smp', 'sma'], collect($levels)->sortBy(fn ($s) => ['sd' => 1, 'smp' => 2, 'sma' => 3][$s])->values()->all(), $komda->name);
        }
    }

    public function test_every_student_has_a_guardian_one_active_enrollment_and_one_class(): void
    {
        $this->assertSame(630, Student::count());
        $this->assertSame(0, Student::doesntHave('guardians')->count());
        $this->assertSame(0, Student::whereDoesntHave('enrollments.placements', fn ($q) => $q->where('status', 'active'))->count());
        $this->assertSame(630, DB::table('student_enrollments')->count());
        $this->assertGreaterThan(0, Employee::where('employee_type', 'teacher')->count());
        $this->assertGreaterThan(0, Employee::where('employee_type', 'staff')->count());
    }

    public function test_running_the_seeder_again_does_not_duplicate_anything(): void
    {
        $tables = ['organizational_units', 'organizational_positions', 'organizational_assignments', 'schools', 'employees',
            'employee_assignments', 'students', 'student_guardians', 'student_enrollments', 'student_class_placements', 'classrooms', 'admins', 'users', 'user_unit_access'];
        $before = array_map(fn ($t) => DB::table($t)->count(), $tables);

        $this->seed(YayasanSeeder::class);

        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->count(), $tables));
    }

    public function test_school_head_is_a_pegawai_account_linked_to_employee_data(): void
    {
        $head = User::where('username', 'kepsek.sd.sulteng.tlt')->firstOrFail();
        $this->assertNotNull($head->employee, 'Kepala sekolah harus terhubung ke data pegawai.');

        $this->post(route('pegawai.login.store'), ['username' => 'kepsek.sd.sulteng.tlt', 'password' => 'Yayasan#2026Demo'])
            ->assertRedirect(route('pegawai.dashboard'));
        $this->assertAuthenticatedAs($head, 'pegawai');
        $this->assertGuest('admin');
        $this->get(route('pegawai.dashboard'))->assertOk();
    }

    public function test_komda_admin_only_sees_employees_inside_their_own_komda(): void
    {
        $komda = Admin::where('username', 'komda.sulteng.tlt')->firstOrFail();

        $this->post(route('admin.login.store'), ['username' => 'komda.sulteng.tlt', 'password' => 'Yayasan#2026Demo'])->assertRedirect();
        $this->assertAuthenticatedAs($komda, 'admin');

        $own = Employee::where('employee_number', 'PEG-01-02')->firstOrFail();
        $other = Employee::where('employee_number', 'PEG-04-02')->firstOrFail();
        $this->get(route('admin.employees.show', $own))->assertOk();
        $this->get(route('admin.employees.show', $other))->assertForbidden();
    }

    public function test_seeder_is_skipped_in_production(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        $before = OrganizationalUnit::count();
        DB::table('student_class_placements')->delete();
        DB::table('student_enrollments')->delete();
        DB::table('student_guardians')->delete();
        DB::table('students')->delete();

        try {
            app(YayasanSeeder::class)->run();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }

        $this->assertSame($before, OrganizationalUnit::count());
        $this->assertSame(0, Student::count());
    }
}
