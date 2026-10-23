<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use Database\Seeders\Concerns\YayasanDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Kelas, pelajar, wali, pendaftaran tahun ajaran, dan penempatan kelas untuk setiap sekolah. */
class YayasanAkademikSeeder extends Seeder
{
    use YayasanDataset;

    /** Jumlah pelajar per kelas contoh. */
    private const PER_CLASS = 10;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->call(YayasanSekolahSeeder::class);

        DB::transaction(function () {
            foreach ($this->schoolSpecs() as $spec) {
                $school = School::whereHas('unit', fn ($q) => $q->where('code', $spec['code']))->firstOrFail();
                $year = AcademicYear::where('school_id', $school->id)->where('name', self::YEAR_NAME)->firstOrFail();

                foreach ($this->levels()[$spec['level']][2] as $c => [$className, $grade, $age]) {
                    $classroom = Classroom::updateOrCreate(
                        ['academic_year_id' => $year->id, 'name' => $className],
                        ['school_id' => $school->id, 'grade_label' => $grade, 'capacity' => 32, 'status' => 'active'],
                    );

                    for ($n = 1; $n <= self::PER_CLASS; $n++) {
                        $this->student($school, $year, $classroom, $spec['index'], $c, $n, $age);
                    }
                }
            }
        });

        $this->command?->info('Pelajar: '.Student::count().', wali: '.StudentGuardian::count().'.');
    }

    private function student(School $school, AcademicYear $year, Classroom $classroom, int $schoolIdx, int $classIdx, int $n, int $age): void
    {
        $seed = $schoolIdx * 1000 + $classIdx * 100 + $n;
        $gender = $n % 2 === 0 ? 'female' : 'male';
        $nis = sprintf('%02d%d%03d', $schoolIdx, $classIdx + 1, $n);

        $student = Student::firstOrCreate(['school_id' => $school->id, 'nis' => $nis], [
            'nisn' => sprintf('30%02d%d%04d', $schoolIdx, $classIdx + 1, $n),
            'name' => $this->personName($seed, $gender), 'gender' => $gender,
            'birth_place' => preg_replace('/^KOMDA\s+(Kabupaten|Kota)\s+/', '', (string) $school->unit->parent?->name) ?: 'Asal',
            'birth_date' => sprintf('%d-%02d-%02d', 2026 - $age, 1 + $seed % 12, 1 + $seed % 28),
            'address' => "Jl. Pelajar No. {$n}", 'status' => 'active',
        ]);

        if (! $student->wasRecentlyCreated) {
            return;
        }

        StudentGuardian::create([
            'student_id' => $student->id, 'name' => $this->personName($seed + 50000, $n % 2 ? 'male' : 'female'),
            'relationship' => $n % 2 ? 'Ayah' : 'Ibu', 'phone' => $this->phone($seed), 'address' => $student->address, 'is_primary' => true,
        ]);

        $enrollment = StudentEnrollment::create([
            'student_id' => $student->id, 'academic_year_id' => $year->id, 'enrollment_date' => '2026-07-13', 'status' => 'active',
        ]);
        StudentClassPlacement::create([
            'student_enrollment_id' => $enrollment->id, 'classroom_id' => $classroom->id, 'start_date' => '2026-07-13', 'status' => 'active',
        ]);
    }
}
