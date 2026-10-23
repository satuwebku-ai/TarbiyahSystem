<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\OrganizationalUnit;
use App\Models\School;
use App\Models\Student;
use Database\Seeders\ContohDataSeeder;
use Database\Seeders\EmployeeTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContohDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_type_seeder_creates_three_types_and_keeps_admin_edits(): void
    {
        $this->seed(EmployeeTypeSeeder::class);
        EmployeeType::where('code', 'pb')->update(['name' => 'Staf Pengurus Besar', 'is_active' => false]);
        $this->seed(EmployeeTypeSeeder::class);

        $this->assertSame(['pb', 'staff', 'teacher'], EmployeeType::orderBy('code')->pluck('code')->all());
        $this->assertSame('Staf Pengurus Besar', EmployeeType::where('code', 'pb')->value('name'));
        $this->assertFalse((bool) EmployeeType::where('code', 'pb')->value('is_active'));
    }

    public function test_sample_data_is_complete_and_idempotent(): void
    {
        $this->seed(ContohDataSeeder::class);
        $this->seed(ContohDataSeeder::class);

        $this->assertSame(6, OrganizationalUnit::where('code', 'like', 'CTH-%')->count());
        $this->assertSame(3, School::count());
        $this->assertSame(15, Student::count());
        $this->assertSame(22, Employee::count());
        $this->assertSame(7, Employee::where('employee_type', 'pb')->count());
        $this->assertSame(12, Employee::where('employee_type', 'teacher')->count());
        $this->assertSame(3, Employee::where('employee_type', 'staff')->count());
        $this->assertSame(15, \App\Models\StudentClassPlacement::count());
    }
}
