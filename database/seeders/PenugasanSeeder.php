<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalAssignment;
use Database\Seeders\Concerns\DataYayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 9. Penugasan organisasi: setiap pegawai yang ditempatkan pada sebuah jabatan dicatat sebagai pemegang jabatan
 * itu (sama seperti saat pegawai didaftarkan lewat menu Pegawai). Idempoten. Dilewati di production.
 */
class PenugasanSeeder extends Seeder
{
    use DataYayasan;

    public function run(): void
    {
        if (! $this->allowed()) {
            return;
        }
        $this->require(Employee::exists(), PegawaiSeeder::class);

        DB::transaction(function () {
            EmployeeAssignment::query()
                ->whereNotNull('organizational_position_id')->whereNull('end_date')
                ->with('employee')->orderBy('id')->get()
                ->each(function (EmployeeAssignment $placement) {
                    OrganizationalAssignment::firstOrCreate(
                        [
                            'organizational_unit_id' => $placement->organizational_unit_id,
                            'organizational_position_id' => $placement->organizational_position_id,
                            'employee_id' => $placement->employee_id,
                            'start_date' => $placement->start_date->toDateString(),
                        ],
                        ['assignee_name' => $placement->employee->name, 'status' => 'active'],
                    );
                });
        });

        $this->command?->info('Penugasan: '.OrganizationalAssignment::count().'.');
    }
}
