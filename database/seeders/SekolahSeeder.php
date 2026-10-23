<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalPosition;
use App\Models\School;
use App\Models\SchoolLevel;
use Database\Seeders\Concerns\DataYayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 4. Data sekolah: 12 sekolah (NPSN, jenjang, kontak), tahun ajaran aktif, dan 3 kelas per sekolah.
 * Idempoten. Dilewati di production.
 */
class SekolahSeeder extends Seeder
{
    use DataYayasan;

    public function run(): void
    {
        if (! $this->allowed()) {
            return;
        }
        $this->require(SchoolLevel::where('slug', 'sma')->exists(), JenjangSekolahSeeder::class);
        $this->require(OrganizationalPosition::exists(), JabatanSeeder::class);

        DB::transaction(function () {
            $levels = SchoolLevel::pluck('id', 'slug');

            foreach ($this->schoolSpecs() as $spec) {
                $unit = OrganizationalUnit::where('code', $spec['code'])->firstOrFail();

                $school = School::updateOrCreate(
                    ['organizational_unit_id' => $unit->id],
                    ['school_level_id' => $levels[$spec['level']], 'npsn' => sprintf('6%07d', $spec['index']),
                        'phone' => $this->phone($spec['index']),
                        'email' => strtolower(str_replace('-', '.', $spec['code'])).'@sekolah.example.test', 'status' => 'active'],
                );

                $year = AcademicYear::updateOrCreate(
                    ['school_id' => $school->id, 'name' => self::YEAR_NAME],
                    ['start_date' => self::YEAR_START, 'end_date' => self::YEAR_END, 'is_active' => true],
                );

                foreach ($this->levels()[$spec['level']][2] as [$className, $grade]) {
                    Classroom::updateOrCreate(
                        ['academic_year_id' => $year->id, 'name' => $className],
                        ['school_id' => $school->id, 'grade_label' => $grade, 'capacity' => 32, 'status' => 'active'],
                    );
                }
            }
        });

        $this->command?->info('Sekolah: '.School::count().' (SD/SMP/SMA), kelas: '.Classroom::count().'.');
    }
}
