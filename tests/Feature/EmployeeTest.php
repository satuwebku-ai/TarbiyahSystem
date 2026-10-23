<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Admin;
use App\Models\Employee;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserUnitAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationalUnit $unitA;
    private OrganizationalUnit $unitB;
    private int $seq = 0;

    private const ALL = [
        'employees.view', 'employees.create', 'employees.update', 'employees.status',
        'employees.assignments.manage', 'employees.link_account', 'employees.documents.view', 'employees.documents.manage',
        'organization.access.all',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $root = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'R', 'name' => 'Yayasan', 'status' => 'active']);
        $this->unitA = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $root->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        $this->unitB = OrganizationalUnit::create(['type_id' => $type->id, 'parent_id' => $root->id, 'code' => 'B', 'name' => 'Sekolah B', 'status' => 'active']);
    }

    public function test_migrations_create_employee_tables_before_attendance(): void
    {
        foreach (['employees', 'employee_assignments', 'employee_documents', 'employee_status_histories', 'attendance_records'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), $table);
        }
    }

    public function test_create_employee_with_primary_assignment_and_history(): void
    {
        $position = OrganizationalPosition::create(['organizational_unit_id' => $this->unitA->id, 'name' => 'Guru', 'is_active' => true]);
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');

        $this->post(route('admin.employees.store'), [
            'employee_number' => 'P-001', 'name' => 'Budi', 'employee_type' => 'teacher',
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $position->id, 'start_date' => '2026-01-01',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $employee = Employee::firstOrFail();
        $this->assertSame(EmployeeStatus::Active, $employee->status);
        $this->assertTrue($employee->assignments()->firstOrFail()->is_primary);
        $this->assertSame(1, $employee->statusHistories()->count());
        $this->get(route('admin.employees.show', $employee))->assertOk()->assertSee('Budi');
        $this->get(route('admin.employees.index'))->assertOk()->assertSee('P-001');
    }

    public function test_duplicate_number_and_position_from_other_unit_are_rejected(): void
    {
        $foreign = OrganizationalPosition::create(['organizational_unit_id' => $this->unitB->id, 'name' => 'Guru', 'is_active' => true]);
        $this->makeEmployee($this->unitA, 'P-001');
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');

        $payload = ['employee_number' => 'P-001', 'name' => 'Xavier', 'employee_type' => 'staff', 'organizational_unit_id' => $this->unitA->id, 'start_date' => '2026-01-01'];
        $this->post(route('admin.employees.store'), $payload)->assertSessionHasErrors('employee_number');
        $this->post(route('admin.employees.store'), array_replace($payload, ['employee_number' => 'P-002', 'organizational_position_id' => $foreign->id]))->assertSessionHasErrors('organizational_position_id');
        $this->assertSame(1, Employee::count());
    }

    public function test_position_on_initial_placement_creates_organizational_assignment(): void
    {
        $position = OrganizationalPosition::create(['organizational_unit_id' => $this->unitA->id, 'name' => 'Kepala Sekolah', 'is_active' => true]);
        $this->actingAs($this->makeAdmin([...self::ALL, 'organization.assignments.manage']), 'admin');

        $payload = ['employee_number' => 'P-100', 'name' => 'Siti', 'employee_type' => 'teacher',
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $position->id, 'start_date' => '2026-01-01'];
        $this->post(route('admin.employees.store'), $payload)->assertSessionHasNoErrors();

        $employee = Employee::where('employee_number', 'P-100')->firstOrFail();
        $this->assertDatabaseHas('organizational_assignments', [
            'employee_id' => $employee->id, 'organizational_position_id' => $position->id, 'assignee_name' => 'Siti', 'status' => 'active',
        ]);

        // Jabatan yang sedang dijabat tidak bisa dipakai pegawai lain.
        $this->post(route('admin.employees.store'), array_replace($payload, ['employee_number' => 'P-101', 'name' => 'Rudi']))
            ->assertSessionHasErrors('organizational_position_id');
        $this->assertSame(1, Employee::count());
    }

    public function test_employee_types_are_managed_and_validated_dynamically(): void
    {
        $this->actingAs($this->makeAdmin([...self::ALL, 'employees.types.manage']), 'admin');

        $this->post(route('admin.employees.types.store'), ['name' => 'Satpam', 'code' => 'satpam', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-200', 'name' => 'Joko', 'employee_type' => 'satpam',
            'organizational_unit_id' => $this->unitA->id, 'start_date' => '2026-01-01'])->assertSessionHasNoErrors();
        $this->assertSame('Satpam', Employee::where('employee_number', 'P-200')->firstOrFail()->typeLabel());

        $type = \App\Models\EmployeeType::where('code', 'satpam')->firstOrFail();
        $this->patch(route('admin.employees.types.toggle', $type));
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-201', 'name' => 'Anto', 'employee_type' => 'satpam',
            'organizational_unit_id' => $this->unitA->id, 'start_date' => '2026-01-01'])->assertSessionHasErrors('employee_type');
        $this->get(route('admin.employees.types.index'))->assertOk()->assertSee('Satpam');
    }

    public function test_multi_holder_position_allows_several_employees_but_single_position_per_employee(): void
    {
        $staff = OrganizationalPosition::create(['organizational_unit_id' => $this->unitA->id, 'name' => 'Staf Kantor', 'capacity' => null, 'is_active' => true]);
        $chair = OrganizationalPosition::create(['organizational_unit_id' => $this->unitA->id, 'name' => 'Ketua', 'capacity' => 1, 'is_active' => true]);
        $this->actingAs($this->makeAdmin([...self::ALL, 'organization.assignments.manage']), 'admin');

        foreach (['P-1' => 'Ani', 'P-2' => 'Budi'] as $no => $name) {
            $this->post(route('admin.employees.store'), ['employee_number' => $no, 'name' => $name, 'employee_type' => 'staff',
                'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $staff->id, 'start_date' => '2026-01-01'])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, $staff->assignments()->count());

        $this->post(route('admin.employees.store'), ['employee_number' => 'P-3', 'name' => 'Cici', 'employee_type' => 'staff',
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $chair->id, 'start_date' => '2026-01-01'])->assertSessionHasNoErrors();
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-4', 'name' => 'Dedi', 'employee_type' => 'staff',
            'organizational_unit_id' => $this->unitA->id, 'organizational_position_id' => $chair->id, 'start_date' => '2026-01-01'])->assertSessionHasErrors('organizational_position_id');

        // Pegawai yang sudah menjabat tidak boleh ditugaskan ke jabatan kedua.
        $ani = Employee::where('employee_number', 'P-1')->firstOrFail();
        $this->post(route('admin.organization.assignments.store'), ['organizational_unit_id' => $this->unitA->id,
            'organizational_position_id' => $chair->id, 'employee_id' => $ani->id, 'start_date' => '2026-02-01'])->assertSessionHasErrors();
    }

    public function test_scoped_admin_sees_and_manages_only_own_unit(): void
    {
        $mine = $this->makeEmployee($this->unitA, 'P-A', 'Guru Alfa');
        $other = $this->makeEmployee($this->unitB, 'P-B', 'Guru Beta');
        $this->actingAs($this->makeAdmin(array_diff(self::ALL, ['organization.access.all']), $this->unitA), 'admin');

        $this->get(route('admin.employees.index'))->assertOk()->assertSee('Guru Alfa')->assertDontSee('Guru Beta');
        $this->get(route('admin.employees.show', $mine))->assertOk();
        $this->get(route('admin.employees.show', $other))->assertForbidden();
        $this->put(route('admin.employees.update', $other), ['employee_number' => 'P-B', 'name' => 'Ubah', 'employee_type' => 'staff'])->assertForbidden();
        $this->post(route('admin.employees.status.store', $other), ['status' => 'retired', 'effective_date' => '2026-10-01'])->assertForbidden();
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-X', 'name' => 'Zainal', 'employee_type' => 'staff', 'organizational_unit_id' => $this->unitB->id, 'start_date' => '2026-01-01'])->assertForbidden();
    }

    public function test_ending_status_closes_open_assignments_and_records_history(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-1');
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');

        $this->post(route('admin.employees.status.store', $employee), ['status' => 'retired', 'effective_date' => '2026-10-01'])->assertSessionHasNoErrors();

        $employee->refresh();
        $this->assertSame(EmployeeStatus::Retired, $employee->status);
        $this->assertSame('2026-10-01', $employee->assignments()->first()->end_date->toDateString());
        $this->assertSame(1, $employee->statusHistories()->count());
        $this->post(route('admin.employees.status.store', $employee), ['status' => 'retired', 'effective_date' => '2026-10-02'])->assertSessionHasErrors('status');
    }

    public function test_account_can_be_created_together_with_the_employee(): void
    {
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');
        $base = [
            'employee_number' => 'P-010', 'name' => 'Sari', 'employee_type' => 'teacher', 'email' => 'sari@example.test',
            'organizational_unit_id' => $this->unitA->id, 'start_date' => '2026-01-01',
        ];

        $this->post(route('admin.employees.store'), $base + [
            'create_account' => '1', 'username' => 'sari.guru', 'password' => 'Secure-Test-Password-2026!', 'password_confirmation' => 'Secure-Test-Password-2026!',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $employee = Employee::where('employee_number', 'P-010')->firstOrFail();
        $this->assertNotNull($employee->user_id);
        $this->assertSame('sari.guru', $employee->user->username);

        // Tanpa centang, pegawai dibuat tanpa akun dan kolom akun tidak divalidasi.
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-011', 'name' => 'Rudi'] + $base)
            ->assertSessionHasNoErrors();
        $this->assertNull(Employee::where('employee_number', 'P-011')->firstOrFail()->user_id);

        // Centang tanpa password gagal dan tidak membuat pegawai setengah jadi.
        $this->post(route('admin.employees.store'), ['employee_number' => 'P-012', 'name' => 'Dewi', 'create_account' => '1'] + $base)
            ->assertSessionHasErrors(['username', 'password']);
        $this->assertDatabaseMissing('employees', ['employee_number' => 'P-012']);
    }

    public function test_account_can_be_created_and_password_reset_from_the_edit_page(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-20', 'Andi');
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');
        $base = ['employee_number' => 'P-20', 'name' => 'Andi', 'employee_type' => $employee->employee_type, 'email' => 'andi@example.test'];

        $this->put(route('admin.employees.update', $employee), $base + [
            'create_account' => '1', 'username' => 'andi.guru',
            'password' => 'Secure-Test-Password-2026!', 'password_confirmation' => 'Secure-Test-Password-2026!',
        ])->assertSessionHasNoErrors();
        $user = $employee->fresh()->user;
        $this->assertSame('andi.guru', $user->username);

        $old = $user->password;
        $this->put(route('admin.employees.update', $employee), $base + [
            'reset_password' => '1',
            'password' => 'Another-Test-Password-2027!', 'password_confirmation' => 'Another-Test-Password-2027!',
        ])->assertSessionHasNoErrors();
        $this->assertNotSame($old, $user->fresh()->password);

        // Centang tanpa password gagal.
        $this->put(route('admin.employees.update', $employee), $base + ['reset_password' => '1'])->assertSessionHasErrors('password');
    }

    public function test_account_link_is_unique_per_employee(): void
    {
        $account = $this->makePegawaiAccount();
        $first = $this->makeEmployee($this->unitA, 'P-1');
        $second = $this->makeEmployee($this->unitA, 'P-2');
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');

        $this->put(route('admin.employees.account.update', $first), ['user_id' => $account->id])->assertSessionHasNoErrors();
        $this->assertSame($account->id, $first->fresh()->user_id);
        $this->put(route('admin.employees.account.update', $second), ['user_id' => $account->id])->assertSessionHasErrors('user_id');
        $this->put(route('admin.employees.account.update', $first), ['user_id' => null]);
        $this->assertNull($first->fresh()->user_id);
    }

    public function test_portal_account_can_be_created_and_linked_from_the_employee_page(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-1', 'Budi Santoso');
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');

        $this->post(route('admin.employees.account.store', $employee), [
            'username' => 'Budi.S', 'email' => 'budi@example.test',
            'password' => 'Secure-Test-Password-2026!', 'password_confirmation' => 'Secure-Test-Password-2026!',
        ])->assertSessionHasNoErrors();

        $user = User::where('username', 'budi.s')->firstOrFail();
        $this->assertSame($user->id, $employee->fresh()->user_id);
        $this->assertSame('Budi Santoso', $user->name);
        $this->assertTrue($user->hasPermission('attendance.self'));

        // pegawai yang sudah punya akun tidak bisa dibuatkan akun kedua
        $this->post(route('admin.employees.account.store', $employee), [
            'username' => 'budi.dua', 'email' => 'budi2@example.test',
            'password' => 'Secure-Test-Password-2026!', 'password_confirmation' => 'Secure-Test-Password-2026!',
        ])->assertStatus(422);
        $this->assertSame(1, User::count());
    }

    public function test_assignment_rules(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-1'); // utama sejak 2026-01-01, berjalan
        $this->actingAs($this->makeAdmin(self::ALL), 'admin');

        $this->post(route('admin.employees.assignments.store', $employee), [
            'organizational_unit_id' => $this->unitB->id, 'is_primary' => 1, 'start_date' => '2026-06-01',
        ])->assertSessionHasErrors('is_primary');

        $this->post(route('admin.employees.assignments.store', $employee), [
            'organizational_unit_id' => $this->unitB->id, 'is_primary' => 0, 'start_date' => '2026-06-01',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $employee->assignments()->count());

        $assignment = $employee->assignments()->where('organizational_unit_id', $this->unitB->id)->first();
        $this->patch(route('admin.employees.assignments.end', [$employee, $assignment]), ['end_date' => '2026-05-01'])->assertSessionHasErrors('end_date');
        $this->patch(route('admin.employees.assignments.end', [$employee, $assignment]), ['end_date' => '2026-09-01'])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-01', $assignment->fresh()->end_date->toDateString());
    }

    public function test_documents_are_private_and_checked_against_employee(): void
    {
        Storage::fake('local');
        $employee = $this->makeEmployee($this->unitA, 'P-1');
        $other = $this->makeEmployee($this->unitB, 'P-2');
        $this->actingAs($this->makeAdmin(array_diff(self::ALL, ['organization.access.all']), $this->unitA), 'admin');

        $this->post(route('admin.employees.documents.store', $employee), [
            'title' => 'SK', 'file' => UploadedFile::fake()->create('sk.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $doc = $employee->documents()->firstOrFail();
        Storage::disk('local')->assertExists($doc->path);

        $this->get(route('admin.employees.documents.download', [$employee, $doc]))->assertOk();
        $this->get(route('admin.employees.documents.download', [$other, $doc]))->assertForbidden();
        $this->post(route('admin.employees.documents.store', $employee), [
            'title' => 'Exe', 'file' => UploadedFile::fake()->create('x.php', 10, 'text/x-php'),
        ])->assertSessionHasErrors('file');

        $this->delete(route('admin.employees.documents.destroy', [$employee, $doc]));
        Storage::disk('local')->assertMissing($doc->path);
    }

    public function test_permissions_are_enforced(): void
    {
        $employee = $this->makeEmployee($this->unitA, 'P-1');
        $this->actingAs($this->makeAdmin(['employees.view']), 'admin');

        $this->get(route('admin.employees.index'))->assertOk();
        $this->get(route('admin.employees.create'))->assertForbidden();
        $this->post(route('admin.employees.status.store', $employee), ['status' => 'resigned', 'effective_date' => '2026-10-01'])->assertForbidden();
        $this->put(route('admin.employees.account.update', $employee), ['user_id' => null])->assertForbidden();
    }

    private function makeEmployee(OrganizationalUnit $unit, string $number, ?string $name = null): Employee
    {
        $employee = Employee::create(['employee_number' => $number, 'name' => $name ?? "Pegawai $number", 'employee_type' => 'teacher', 'status' => EmployeeStatus::Active]);
        $employee->assignments()->create(['organizational_unit_id' => $unit->id, 'is_primary' => true, 'start_date' => '2026-01-01']);

        return $employee;
    }

    private function makePegawaiAccount(): User
    {
        $n = ++$this->seq;

        return User::create(['name' => "Akun $n", 'username' => "pegawai$n", 'email' => "pegawai$n@example.test", 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
    }

    private function makeAdmin(array $permissions, ?OrganizationalUnit $scope = null): Admin
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => "role-$n", 'label' => "Role $n", 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }
        $admin = Admin::create(['name' => "Admin $n", 'username' => "adm$n", 'email' => "adm$n@example.test", 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);
        if ($scope) {
            UserUnitAccess::create(['organizational_unit_id' => $scope->id, 'model_type' => Admin::class, 'model_id' => $admin->id, 'created_at' => now()]);
        }

        return $admin;
    }
}
