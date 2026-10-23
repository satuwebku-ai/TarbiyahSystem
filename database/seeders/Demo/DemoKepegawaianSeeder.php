<?php

namespace Database\Seeders\Demo;

use App\Models\Employee;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Pegawai: kepala sekolah, guru, tenaga administrasi tiap sekolah, dan staf sekretariat tiap tingkat organisasi. */
class DemoKepegawaianSeeder extends Seeder
{
    use DemoDataset;

    private const SUBJECTS = [
        'sd' => ['Guru Kelas 1', 'Guru Kelas 3', 'Guru Kelas 5', 'Guru PAI', 'Guru PJOK'],
        'smp' => ['Guru Matematika', 'Guru Bahasa Indonesia', 'Guru IPA', 'Guru Bahasa Inggris', 'Guru PAI'],
        'sma' => ['Guru Matematika', 'Guru Fisika', 'Guru Biologi', 'Guru Bahasa Inggris', 'Guru Sejarah'],
    ];

    /** Nomor pegawai: PEG-<kode sekolah>-NN. NN 01 = kepala sekolah, 02 = wakil, 03-07 = guru, 08 = tenaga administrasi. */
    public static function number(string $schoolCode, int $n): string
    {
        return sprintf('PEG-%s-%02d', $schoolCode, $n);
    }

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->ensure(School::exists(), DemoSekolahSeeder::class);

        DB::transaction(function () {
            // Staf sekretariat untuk PB, setiap KOMWIL dan KOMDA.
            $n = 0;
            OrganizationalUnit::whereHas('type', fn ($q) => $q->whereIn('slug', ['pengurus-besar', 'komwil', 'komda']))
                ->orderBy('code')->get()->each(function (OrganizationalUnit $unit) use (&$n) {
                    $n++;
                    $this->seedEmployee("STF-{$unit->code}", $this->personName(500 + $n, $n % 2 ? 'female' : 'male'), 'staff', $unit, null, "Staf sekretariat {$unit->name}", 3000 + $n);
                });

            foreach ($this->schoolSpecs() as $spec) {
                $unit = OrganizationalUnit::where('code', $spec['code'])->firstOrFail();
                $position = fn (string $name) => OrganizationalPosition::where('organizational_unit_id', $unit->id)->where('name', $name)->firstOrFail();
                $base = $spec['index'] * 20;
                $code = $spec['code'];

                $head = $this->seedEmployee(self::number($code, 1), $this->personName($base + 1), 'teacher', $unit, $position('Kepala Sekolah'), 'Kepala Sekolah', $base + 1);
                $this->seedEmployee(self::number($code, 2), $this->personName($base + 2, 'female'), 'teacher', $unit, $position('Wakil Kepala Sekolah'), 'Wakil Kepala Sekolah', $base + 2);
                foreach (self::SUBJECTS[$spec['level']] as $i => $subject) {
                    $seed = $base + 3 + $i;
                    $this->seedEmployee(self::number($code, 3 + $i), $this->personName($seed, $seed % 2 ? 'female' : 'male'), 'teacher', $unit, $position('Guru'), $subject, $seed);
                }
                $this->seedEmployee(self::number($code, 8), $this->personName($base + 8, 'female'), 'staff', $unit, $position('Tenaga Administrasi'), 'Tenaga Administrasi', $base + 8);

                // Kepala sekolah juga tercatat sebagai pengurus unit (penugasan organisasi).
                $assignment = OrganizationalAssignment::firstOrCreate(
                    ['organizational_unit_id' => $unit->id, 'organizational_position_id' => $position('Kepala Sekolah')->id, 'start_date' => '2026-01-01'],
                    ['employee_id' => $head->id, 'assignee_name' => $head->name, 'status' => 'active'],
                );
                if (! $assignment->employee_id) {
                    $assignment->update(['employee_id' => $head->id]);
                }
            }
        });

        $this->command?->info('Pegawai: '.Employee::count().' (guru dan tenaga kependidikan).');
    }
}
