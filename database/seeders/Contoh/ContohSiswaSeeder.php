<?php

namespace Database\Seeders\Contoh;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\OrganizationalUnit;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Satu kelas per sekolah contoh dengan 5 siswa, wali utama, pendaftaran tahun ajaran, dan penempatan kelas. */
class ContohSiswaSeeder extends Seeder
{
    use ContohData;

    public const PER_KELAS = 5;

    public function run(): void
    {
        if (! $this->bolehJalan()) {
            return;
        }
        if (! OrganizationalUnit::where('code', 'CTH-SD')->exists()) {
            $this->call(ContohSekolahSeeder::class);
        }

        DB::transaction(function () {
            $no = 0;
            foreach ($this->sekolah() as $urut => [$kode, , , $namaKelas, $tingkat, $umur]) {
                $sekolah = School::whereHas('unit', fn ($q) => $q->where('code', $kode))->firstOrFail();
                $tahun = AcademicYear::where('school_id', $sekolah->id)->where('name', self::YEAR)->firstOrFail();
                $kelas = Classroom::updateOrCreate(
                    ['academic_year_id' => $tahun->id, 'name' => $namaKelas],
                    ['school_id' => $sekolah->id, 'grade_label' => $tingkat, 'capacity' => 30, 'status' => 'active'],
                );

                for ($n = 1; $n <= self::PER_KELAS; $n++) {
                    $no++;
                    $gender = $n % 2 === 0 ? 'female' : 'male';
                    $siswa = Student::firstOrCreate(['school_id' => $sekolah->id, 'nis' => sprintf('C%02d%03d', $no, $n)], [
                        'nisn' => sprintf('99%08d', $no),
                        'name' => $this->nama($no + 3, $gender), 'gender' => $gender, 'birth_place' => 'Palu',
                        'birth_date' => sprintf('%d-%02d-%02d', 2026 - $umur, 1 + $no % 12, 1 + $no % 28),
                        'address' => "Jl. Pelajar Contoh No. {$no}", 'status' => 'active',
                    ]);
                    if (! $siswa->wasRecentlyCreated) {
                        continue;
                    }

                    StudentGuardian::create([
                        'student_id' => $siswa->id, 'name' => $this->nama($no + 20, $n % 2 ? 'male' : 'female'),
                        'relationship' => $n % 2 ? 'Ayah' : 'Ibu', 'phone' => $this->telepon($no), 'address' => $siswa->address, 'is_primary' => true,
                    ]);
                    $daftar = StudentEnrollment::create([
                        'student_id' => $siswa->id, 'academic_year_id' => $tahun->id, 'enrollment_date' => '2026-07-13', 'status' => 'active',
                    ]);
                    StudentClassPlacement::create([
                        'student_enrollment_id' => $daftar->id, 'classroom_id' => $kelas->id, 'start_date' => '2026-07-13', 'status' => 'active',
                    ]);
                }
            }
        });

        $this->command?->info('Siswa contoh: '.(count($this->sekolah()) * self::PER_KELAS).' (3 kelas).');
    }
}
