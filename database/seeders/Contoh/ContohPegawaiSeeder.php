<?php

namespace Database\Seeders\Contoh;

use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use Database\Seeders\EmployeeTypeSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pegawai contoh dengan tiga jenis:
 *   Pegawai PB          : pengurus PB, KOMWIL, KOMDA (jenis "pb")
 *   Guru                : kepala sekolah, wakil, dan 2 guru per sekolah (jenis "teacher")
 *   Tenaga kependidikan : 1 tenaga administrasi per sekolah (jenis "staff")
 */
class ContohPegawaiSeeder extends Seeder
{
    use ContohData;

    public function run(): void
    {
        if (! $this->bolehJalan()) {
            return;
        }
        $this->call(EmployeeTypeSeeder::class);
        if (! OrganizationalUnit::where('code', self::PB)->exists()) {
            $this->call(ContohUnitOrganisasiSeeder::class);
        }
        if (! OrganizationalUnit::where('code', 'CTH-SD')->exists()) {
            $this->call(ContohSekolahSeeder::class);
        }

        DB::transaction(function () {
            $seed = 0;

            // Pegawai PB: satu orang per jabatan pengurus.
            foreach ([self::PB, self::KOMWIL, self::KOMDA] as $kode) {
                $unit = OrganizationalUnit::where('code', $kode)->firstOrFail();
                foreach (OrganizationalPosition::where('organizational_unit_id', $unit->id)->orderBy('id')->get() as $i => $jabatan) {
                    $seed++;
                    $this->buatPegawai(sprintf('CTH-%s-%02d', substr($kode, 4), $i + 1), $this->nama($seed, $seed % 3 === 0 ? 'female' : 'male'),
                        'pb', $unit, $jabatan, $jabatan->name, $seed);
                }
            }

            // Guru dan tenaga kependidikan per sekolah.
            foreach ($this->sekolah() as [$kode]) {
                $unit = OrganizationalUnit::where('code', $kode)->firstOrFail();
                $jabatan = fn (string $nama) => OrganizationalPosition::where('organizational_unit_id', $unit->id)->where('name', $nama)->firstOrFail();

                $baris = [
                    ['01', 'Kepala Sekolah', 'teacher', 'Kepala Sekolah', 'male'],
                    ['02', 'Wakil Kepala Sekolah', 'teacher', 'Wakil Kepala Sekolah', 'female'],
                    ['03', 'Guru', 'teacher', 'Guru Matematika', 'male'],
                    ['04', 'Guru', 'teacher', 'Guru Bahasa Indonesia', 'female'],
                    ['05', 'Tenaga Administrasi', 'staff', 'Tenaga Administrasi', 'female'],
                ];
                foreach ($baris as [$no, $namaJabatan, $jenis, $catatan, $gender]) {
                    $seed++;
                    $this->buatPegawai("{$kode}-{$no}", $this->nama($seed, $gender), $jenis, $unit, $jabatan($namaJabatan), $catatan, $seed);
                }
            }
        });

        $this->command?->info('Pegawai contoh dibuat (Pegawai PB, Guru, Tenaga kependidikan).');
    }
}
