<?php

namespace Database\Seeders;

use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Database\Seeders\Concerns\YayasanDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pengurus Besar -> Komisaris Wilayah (KOMWIL, tingkat provinsi) -> Komisaris Daerah (KOMDA,
 * kabupaten/kota), lengkap dengan jabatan dan penugasan pengurusnya. Idempoten.
 */
class YayasanStrukturSeeder extends Seeder
{
    use YayasanDataset;

    private int $seed = 0;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }

        DB::transaction(function () {
            $types = [];
            foreach ([
                'pengurus-besar' => ['Pengurus Besar', 'Pimpinan tertinggi yayasan.'],
                'komwil' => ['Komisaris Wilayah (KOMWIL)', 'Perwakilan yayasan setingkat provinsi.'],
                'komda' => ['Komisaris Daerah (KOMDA)', 'Perwakilan yayasan setingkat kabupaten/kota.'],
                'sekolah' => ['Sekolah', 'Satuan pendidikan (SD, SMP, SMA).'],
            ] as $slug => [$name, $desc]) {
                $types[$slug] = OrganizationalUnitType::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $desc, 'is_active' => true]);
            }

            $pb = $this->unit(self::PB_CODE, $types['pengurus-besar']->id, null, 'Pengurus Besar Yayasan Pendidikan', 'Jl. Pendidikan No. 1, Jakarta');
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

    /**
     * Buat jabatan dan isi setiap jabatan dengan satu pengurus. Pengurus adalah pegawai (Data Pegawai,
     * tipe tenaga kependidikan) yang ditempatkan di unit itu, sehingga penugasan terhubung ke pegawai.
     */
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
                'staff', $unit, $position, $positionName, $this->seed + 100,
            );

            $assignment = OrganizationalAssignment::firstOrCreate(
                ['organizational_unit_id' => $unit->id, 'organizational_position_id' => $position->id, 'start_date' => '2026-01-01'],
                ['employee_id' => $employee->id, 'assignee_name' => $employee->name, 'status' => 'active'],
            );
            // Data contoh lama (nama manual) dihubungkan ke pegawai tanpa mengganti namanya.
            if (! $assignment->employee_id) {
                $assignment->update(['employee_id' => $employee->id]);
            }
        }
    }
}
