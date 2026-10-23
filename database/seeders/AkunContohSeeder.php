<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Models\User;
use App\Services\PegawaiPortalAccess;
use Database\Seeders\Concerns\DataYayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Satu akun login contoh untuk portal Pegawai dan portal Pelajar (password tertulis di kode: hanya data contoh,
 * dilewati di production kecuali SEED_SAMPLE_DATA=true):
 *
 *   Portal Pegawai  : username guru.contoh  | password Contoh#2026Login  (guru pertama SD Tarbiyah Palu)
 *   Portal Pelajar  : NIS      011001       | password Contoh#2026Login  (siswa pertama kelas 1A SD Tarbiyah Palu)
 *
 * Pegawai dan siswa lain tidak diberi akun: pegawai dibuatkan akunnya dari menu Pegawai, siswa mendaftar sendiri lewat NIS.
 * Idempoten: password akun yang sudah ada tidak ditimpa.
 */
class AkunContohSeeder extends Seeder
{
    use DataYayasan;

    public const PASSWORD = 'Contoh#2026Login';
    public const PEGAWAI_USERNAME = 'guru.contoh';
    public const PELAJAR_NIS = '011001';

    public function run(): void
    {
        if (! $this->allowed()) {
            return;
        }
        $this->require(Employee::exists(), PegawaiSeeder::class);
        $this->require(Student::exists(), SiswaSeeder::class);
        $this->require(Role::where('guard_name', 'pelajar')->where('name', 'pelajar')->exists(), RbacSeeder::class);

        DB::transaction(function () {
            $this->pegawai();
            $this->pelajar();
        });

        $this->command?->info('Akun contoh: pegawai '.self::PEGAWAI_USERNAME.' dan pelajar NIS '.self::PELAJAR_NIS.' (password: '.self::PASSWORD.').');
    }

    private function pegawai(): void
    {
        $employee = Employee::where('employee_number', PegawaiSeeder::schoolNumber($this->schoolSpecs()[0]['code'], 3))->firstOrFail();

        $user = User::firstOrCreate(['username' => self::PEGAWAI_USERNAME], [
            'name' => $employee->name, 'email' => self::PEGAWAI_USERNAME.'@pegawai.example.test',
            'password' => self::PASSWORD, 'is_active' => true,
        ]);
        $user->syncRoles([app(PegawaiPortalAccess::class)->defaultRole()->id]);

        if (! $employee->user_id && ! Employee::where('user_id', $user->id)->exists()) {
            $employee->update(['user_id' => $user->id]);
        }
    }

    private function pelajar(): void
    {
        $student = Student::where('nis', self::PELAJAR_NIS)->orderBy('id')->firstOrFail();

        $account = StudentAccount::firstOrCreate(['nis' => self::PELAJAR_NIS], [
            'name' => $student->name, 'email' => 'pelajar.contoh@example.test', 'password' => self::PASSWORD, 'is_active' => true,
        ]);
        if ($role = Role::where('guard_name', 'pelajar')->where('name', 'pelajar')->first()) {
            $account->syncRoles([$role->id]);
        }

        if (! $student->student_account_id && ! Student::where('student_account_id', $account->id)->exists()) {
            $student->forceFill(['student_account_id' => $account->id])->save();
        }
    }
}
