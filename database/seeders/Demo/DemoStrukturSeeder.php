<?php

namespace Database\Seeders\Demo;

use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Database\Seeders\OrganizationTypeSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pengurus Besar -> Komisaris Wilayah (KOMWIL, provinsi) -> Komisaris Daerah (KOMDA, kabupaten/kota),
 * lengkap dengan jabatan dan penugasan pengurusnya. Pengurus adalah pegawai berjenis Pegawai PB. Idempoten.
 */
class DemoStrukturSeeder extends Seeder
{
    use DemoDataset;

    private int $seed = 0;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }
        $this->ensure(OrganizationalUnitType::where('slug', 'sekolah')->exists(), OrganizationTypeSeeder::class);

        DB::transaction(function () {
            $types = OrganizationalUnitType::whereIn('slug', array_keys(OrganizationTypeSeeder::TYPES))->get()->keyBy('slug');

            $pb = $this->unit(self::PB_CODE, $types['pengurus-besar']->id, null, 'Pengurus Besar Yayasan Tarbiyah', 'Jl. Pendidikan No. 1, Jakarta');
            $this->staff($pb, ['Ketua Umum', 'Sekretaris Umum', 'Bendahara Umum', 'Ketua Bidang Pendidikan']);

            foreach ($this->provinces() as [$provCode, $provName, $komdas]) {
                $komwil = $this->unit("KOMWIL-{$provCode}", $types['komwil']->id, $pb->id, "KOMWIL {$provName}", "Kantor Komisaris Wilayah, {$provName}");
                $this->staff($komwil, ['Komisaris Wilayah', 'Sekretaris Wilayah', 'Bendahara Wilayah']);

                foreach ($komdas as [$komdaCode, $komdaName]) {
                    $komda = $this->unit("KOMDA-{$provCode}-{$komdaCode}", $types['komda']->id, $komwil->id, "KOMDA {$komdaName}", "Kantor Komisaris Daerah, {$komdaName}");
                    $this->staff($komda, ['Komisaris Daerah', 'Sekretaris Daerah', 'Bendahara Daerah']);
                }
            }
        });

        $this->command?->info('Struktur organisasi: '.OrganizationalUnit::count().' unit, '.OrganizationalAssignment::count().' penugasan.');
    }

    private function unit(string $code, int $typeId, ?int $parentId, string $name, string $address): OrganizationalUnit
    {
        return OrganizationalUnit::updateOrCreate(
            ['code' => $code],
            ['type_id' => $typeId, 'parent_id' => $parentId, 'name' => $name, 'address' => $address, 'status' => 'active',
                'description' => 'Data contoh yayasan pendidikan.'],
        );
    }

    /** Buat jabatan lalu isi tiap jabatan dengan satu pengurus yang ditempatkan di unit itu. */
    private function staff(OrganizationalUnit $unit, array $positions): void
    {
        foreach ($positions as $i => $positionName) {
            $this->seed++;
            $position = OrganizationalPosition::firstOrCreate(
                ['organizational_unit_id' => $unit->id, 'name' => $positionName],
                ['is_active' => true],
            );
            $gender = str_starts_with($positionName, 'Bendahara') ? 'female' : 'male';
            $employee = $this->seedEmployee(
                sprintf('PGS-%s-%d', $unit->code, $i + 1),
                $this->personName($this->seed + 100, $gender),
                'pb', $unit, $position, $positionName, $this->seed + 100,
            );

            $assignment = OrganizationalAssignment::firstOrCreate(
                ['organizational_unit_id' => $unit->id, 'organizational_position_id' => $position->id, 'start_date' => '2026-01-01'],
                ['employee_id' => $employee->id, 'assignee_name' => $employee->name, 'status' => 'active'],
            );
            if (! $assignment->employee_id) {
                $assignment->update(['employee_id' => $employee->id]);
            }
        }
    }
}
