<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AttendancePolicy;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\School;
use App\Models\SchoolLevel;
use Database\Seeders\Concerns\YayasanDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Jenjang SD/SMP/SMA, sekolah di setiap KOMDA, tahun ajaran, jabatan sekolah, dan jam kerja umum. */
class YayasanSekolahSeeder extends Seeder
{
    use YayasanDataset;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->call(YayasanStrukturSeeder::class);

        DB::transaction(function () {
            $levels = [];
            foreach ($this->levels() as $slug => [$name, $order]) {
                $levels[$slug] = SchoolLevel::firstOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $order, 'is_active' => true]);
            }

            $typeId = OrganizationalUnitType::where('slug', 'sekolah')->value('id');

            foreach ($this->schoolSpecs() as $spec) {
                $komda = OrganizationalUnit::where('code', $spec['komda_code'])->firstOrFail();
                $unit = OrganizationalUnit::updateOrCreate(
                    ['code' => $spec['code']],
                    ['type_id' => $typeId, 'parent_id' => $komda->id, 'name' => $spec['name'], 'status' => 'active',
                        'address' => "Jl. Pendidikan No. {$spec['index']}, {$spec['komda_short']}", 'description' => 'Data contoh yayasan pendidikan.'],
                );

                $school = School::updateOrCreate(
                    ['organizational_unit_id' => $unit->id],
                    ['school_level_id' => $levels[$spec['level']]->id, 'npsn' => sprintf('6%07d', $spec['index']),
                        'phone' => $this->phone($spec['index']), 'email' => strtolower(str_replace('-', '.', $spec['code'])).'@sekolah.example.test', 'status' => 'active'],
                );

                foreach (['Kepala Sekolah', 'Wakil Kepala Sekolah', 'Guru', 'Tenaga Administrasi'] as $position) {
                    OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $unit->id, 'name' => $position], ['is_active' => true]);
                }

                AcademicYear::updateOrCreate(
                    ['school_id' => $school->id, 'name' => self::YEAR_NAME],
                    ['start_date' => '2026-07-13', 'end_date' => '2027-06-26', 'is_active' => true],
                );
            }

            // Jam kerja umum yayasan; tidak menimpa kebijakan umum yang sudah dibuat pengelola.
            if (! AttendancePolicy::whereNull('organizational_unit_id')->where('is_active', true)->exists()) {
                AttendancePolicy::create([
                    'organizational_unit_id' => null, 'name' => 'Jam Kerja Yayasan', 'work_days' => [1, 2, 3, 4, 5],
                    'work_start' => '07:00', 'work_end' => '15:00', 'late_tolerance_minutes' => 15, 'is_active' => true,
                ]);
            }
        });

        $this->command?->info('Sekolah: '.School::count().' (SD/SMP/SMA).');
    }
}
