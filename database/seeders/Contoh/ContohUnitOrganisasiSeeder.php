<?php

namespace Database\Seeders\Contoh;

use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Database\Seeders\OrganizationTypeSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Unit organisasi contoh: Pengurus Besar -> KOMWIL -> KOMDA, lengkap dengan jabatan pengurus. */
class ContohUnitOrganisasiSeeder extends Seeder
{
    use ContohData;

    public function run(): void
    {
        if (! $this->bolehJalan()) {
            return;
        }
        if (! OrganizationalUnitType::where('slug', 'sekolah')->exists()) {
            $this->call(OrganizationTypeSeeder::class);
        }

        DB::transaction(function () {
            $tipe = OrganizationalUnitType::pluck('id', 'slug');

            $pb = $this->unit(self::PB, $tipe['pengurus-besar'], null, 'Pengurus Besar Yayasan Tarbiyah (Contoh)', 'Jl. Pendidikan No. 1, Jakarta');
            $komwil = $this->unit(self::KOMWIL, $tipe['komwil'], $pb->id, 'KOMWIL Sulawesi Tengah (Contoh)', 'Jl. Wilayah No. 2, Palu');
            $komda = $this->unit(self::KOMDA, $tipe['komda'], $komwil->id, 'KOMDA Kota Palu (Contoh)', 'Jl. Daerah No. 3, Palu');

            foreach ([[$pb, ['Ketua Umum', 'Sekretaris Umum', 'Bendahara Umum']],
                [$komwil, ['Komisaris Wilayah', 'Sekretaris Wilayah']],
                [$komda, ['Komisaris Daerah', 'Sekretaris Daerah']]] as [$unit, $jabatan]) {
                foreach ($jabatan as $nama) {
                    OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $unit->id, 'name' => $nama], ['is_active' => true]);
                }
            }
        });

        $this->command?->info('Unit organisasi contoh: PB, KOMWIL, KOMDA.');
    }

    private function unit(string $kode, int $tipeId, ?int $parentId, string $nama, string $alamat): OrganizationalUnit
    {
        return OrganizationalUnit::updateOrCreate(
            ['code' => $kode],
            ['type_id' => $tipeId, 'parent_id' => $parentId, 'name' => $nama, 'address' => $alamat, 'status' => 'active', 'description' => 'Data contoh.'],
        );
    }
}
