<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use Database\Seeders\Concerns\DataYayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 2. Data pegawai beserta penempatan awal (unit + jabatan) dan riwayat statusnya:
 *   Pegawai PB          : satu orang per jabatan di PB, majelis, KOMWIL, KOMDA (PGS-...) + 1 staf sekretariat per PB/KOMWIL/KOMDA (STF-...)
 *   Guru                : kepala sekolah, wakil, dan 5 guru per sekolah (PEG-<kode sekolah>-01..07)
 *   Tenaga kependidikan : 1 tenaga administrasi per sekolah (PEG-<kode sekolah>-08)
 * Penugasan organisasinya dibuat oleh PenugasanSeeder. Idempoten menurut nomor pegawai. Dilewati di production.
 */
class PegawaiSeeder extends Seeder
{
    use DataYayasan;

    private int $seed = 0;

    public static function schoolNumber(string $schoolCode, int $n): string
    {
        return sprintf('PEG-%s-%02d', $schoolCode, $n);
    }

    public function run(): void
    {
        if (! $this->allowed()) {
            return;
        }
        $this->call(JenisPegawaiSeeder::class);
        $this->require(OrganizationalPosition::exists(), JabatanSeeder::class);

        DB::transaction(function () {
            $units = OrganizationalUnit::with('type')->orderBy('id')->get();

            // Pegawai PB: pengurus per jabatan dan staf sekretariat.
            foreach ($units->filter(fn ($u) => $u->type->slug !== 'sekolah') as $unit) {
                $positions = OrganizationalPosition::where('organizational_unit_id', $unit->id)->orderBy('id')->get();
                foreach ($positions as $i => $position) {
                    $this->seed++;
                    $gender = str_starts_with($position->name, 'Bendahara') ? 'female' : 'male';
                    $this->employee(sprintf('PGS-%s-%d', $unit->code, $i + 1), $this->personName($this->seed + 100, $gender), 'pb', $unit, $position, $position->name, $this->seed + 100);
                }
                if (in_array($unit->type->slug, ['pengurus-besar', 'komwil', 'komda'], true)) {
                    $this->seed++;
                    $this->employee("STF-{$unit->code}", $this->personName(500 + $this->seed, $this->seed % 2 ? 'female' : 'male'), 'pb', $unit, null, "Staf sekretariat {$unit->name}", 3000 + $this->seed);
                }
            }

            // Guru dan tenaga kependidikan per sekolah.
            foreach ($this->schoolSpecs() as $spec) {
                $unit = $units->firstWhere('code', $spec['code']);
                $position = fn (string $name) => OrganizationalPosition::where('organizational_unit_id', $unit->id)->where('name', $name)->firstOrFail();
                $base = $spec['index'] * 20;
                $code = $spec['code'];

                $this->employee(self::schoolNumber($code, 1), $this->personName($base + 1), 'teacher', $unit, $position('Kepala Sekolah'), 'Kepala Sekolah', $base + 1);
                $this->employee(self::schoolNumber($code, 2), $this->personName($base + 2, 'female'), 'teacher', $unit, $position('Wakil Kepala Sekolah'), 'Wakil Kepala Sekolah', $base + 2);
                foreach ($this->subjects()[$spec['level']] as $i => $subject) {
                    $seed = $base + 3 + $i;
                    $this->employee(self::schoolNumber($code, 3 + $i), $this->personName($seed, $seed % 2 ? 'female' : 'male'), 'teacher', $unit, $position('Guru'), $subject, $seed);
                }
                $this->employee(self::schoolNumber($code, 8), $this->personName($base + 8, 'female'), 'staff', $unit, $position('Tenaga Administrasi'), 'Tenaga Administrasi', $base + 8);
            }
        });

        $this->command?->info('Pegawai: '.Employee::count().' (Pegawai PB, Guru, Tenaga kependidikan).');
    }

    private function employee(string $number, string $name, string $type, OrganizationalUnit $unit, ?OrganizationalPosition $position, string $note, int $seed): void
    {
        $employee = Employee::firstOrCreate(['employee_number' => $number], [
            'name' => $name, 'employee_type' => $type, 'status' => EmployeeStatus::Active,
            'email' => strtolower($number).'@pegawai.example.test', 'phone' => $this->phone($seed),
            'address' => "Jl. Pegawai No. {$seed}", 'hire_date' => (2012 + $seed % 12).'-07-15', 'notes' => $note,
        ]);

        if (! $employee->wasRecentlyCreated) {
            return;
        }

        $hired = $employee->hire_date->toDateString();
        $employee->assignments()->create([
            'organizational_unit_id' => $unit->id, 'organizational_position_id' => $position?->id,
            'is_primary' => true, 'start_date' => min($hired, self::START_PENUGASAN),
        ]);
        EmployeeStatusHistory::create([
            'employee_id' => $employee->id, 'from_status' => null, 'to_status' => EmployeeStatus::Active->value,
            'effective_date' => $hired, 'reason' => 'Data contoh.',
        ]);
    }
}
