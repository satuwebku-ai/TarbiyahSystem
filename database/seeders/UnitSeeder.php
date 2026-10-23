<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Database\Seeders\Concerns\DataYayasan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 7. Unit organisasi contoh:
 *   Pengurus Besar -> Majelis Kelembagaan -> 12 majelis
 *   Pengurus Besar -> 2 KOMWIL -> 4 KOMDA -> 12 sekolah (SD, SMP, SMA di tiap KOMDA)
 * Idempoten. Dilewati di production.
 */
class UnitSeeder extends Seeder
{
    use DataYayasan;

    public function run(): void
    {
        if (! $this->allowed()) {
            return;
        }
        $this->require(OrganizationalUnitType::where('slug', 'sekolah')->exists() && OrganizationalUnitType::where('slug', 'majelis')->exists(), JenisUnitSeeder::class);

        DB::transaction(function () {
            $type = OrganizationalUnitType::pluck('id', 'slug');

            $pb = $this->unit(self::PB_CODE, $type['pengurus-besar'], null, 'Pengurus Besar Yayasan Tarbiyah', 'Jl. Pendidikan No. 1, Jakarta');

            $majelis = $this->unit(self::MAJELIS_CODE, $type['majelis'], $pb->id, 'Majelis Kelembagaan', null);
            foreach (self::MAJELIS as $i => $name) {
                $this->unit(sprintf('%s-%02d', self::MAJELIS_CODE, $i + 1), $type['majelis'], $majelis->id, $name, null);
            }

            foreach ($this->provinces() as [$provCode, $provName, $komdas]) {
                $komwil = $this->unit("KOMWIL-{$provCode}", $type['komwil'], $pb->id, "KOMWIL {$provName}", "Kantor Komisaris Wilayah, {$provName}");
                foreach ($komdas as [$komdaCode, $komdaName]) {
                    $this->unit("KOMDA-{$provCode}-{$komdaCode}", $type['komda'], $komwil->id, "KOMDA {$komdaName}", "Kantor Komisaris Daerah, {$komdaName}");
                }
            }

            foreach ($this->schoolSpecs() as $spec) {
                $komda = OrganizationalUnit::where('code', $spec['komda_code'])->firstOrFail();
                $this->unit($spec['code'], $type['sekolah'], $komda->id, $spec['name'], "Jl. Pendidikan No. {$spec['index']}, {$spec['komda_short']}");
            }
        });

        $this->command?->info('Unit organisasi: '.OrganizationalUnit::count().' unit.');
    }

    private function unit(string $code, int $typeId, ?int $parentId, string $name, ?string $address): OrganizationalUnit
    {
        return OrganizationalUnit::updateOrCreate(
            ['code' => $code],
            ['type_id' => $typeId, 'parent_id' => $parentId, 'name' => $name, 'address' => $address, 'status' => 'active', 'description' => 'Data contoh yayasan pendidikan.'],
        );
    }
}
