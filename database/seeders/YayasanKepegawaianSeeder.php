<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use Database\Seeders\Concerns\YayasanDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Pegawai: kepala sekolah, guru, tenaga administrasi tiap sekolah, dan staf sekretariat tiap tingkat organisasi. */
class YayasanKepegawaianSeeder extends Seeder
{
    use YayasanDataset;

    private const SUBJECTS = [
        'sd' => ['Guru Kelas 1', 'Guru Kelas 3', 'Guru Kelas 5', 'Guru PAI', 'Guru PJOK'],
        'smp' => ['Guru Matematika', 'Guru Bahasa Indonesia', 'Guru IPA', 'Guru Bahasa Inggris', 'Guru PAI'],
        'sma' => ['Guru Matematika', 'Guru Fisika', 'Guru Biologi', 'Guru Bahasa Inggris', 'Guru Sejarah'],
    ];

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->call(YayasanSekolahSeeder::class);

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
                $position = fn (string $name) => OrganizationalPosition::where('organizational_unit_id', $unit->id)->where('name', $name)->first();
                $base = $spec['index'] * 20;
                $prefix = sprintf('PEG-%02d', $spec['index']);

                $this->seedEmployee("{$prefix}-01", $this->personName($base + 1), 'teacher', $unit, $position('Kepala Sekolah'), 'Kepala Sekolah', $base + 1);
                $this->seedEmployee("{$prefix}-02", $this->personName($base + 2, 'female'), 'teacher', $unit, $position('Wakil Kepala Sekolah'), 'Wakil Kepala Sekolah', $base + 2);
                foreach (self::SUBJECTS[$spec['level']] as $i => $subject) {
                    $seed = $base + 3 + $i;
                    $this->seedEmployee(sprintf('%s-%02d', $prefix, 3 + $i), $this->personName($seed, $seed % 2 ? 'female' : 'male'), 'teacher', $unit, $position('Guru'), $subject, $seed);
                }
                $this->seedEmployee(sprintf('%s-%02d', $prefix, 8), $this->personName($base + 8, 'female'), 'staff', $unit, $position('Tenaga Administrasi'), 'Tenaga Administrasi', $base + 8);

                // Kepala sekolah juga tercatat sebagai pengurus unit (penugasan organisasi).
                $head = Employee::where('employee_number', "{$prefix}-01")->first();
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
