<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\YayasanDataset;
use Illuminate\Database\Seeder;

/**
 * Seeder lengkap yayasan pendidikan (data contoh/fiktif):
 *   php artisan db:seed --class=YayasanSeeder
 *
 * Urutan: struktur organisasi -> sekolah -> pegawai -> pelajar -> akun demo. Aman diulang.
 * Dilewati otomatis di produksi. Seeder tahap dapat dijalankan sendiri, mis.
 *   php artisan db:seed --class=YayasanStrukturSeeder
 */
class YayasanSeeder extends Seeder
{
    use YayasanDataset;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }

        $this->call([
            YayasanStrukturSeeder::class,
            YayasanSekolahSeeder::class,
            YayasanKepegawaianSeeder::class,
            YayasanAkademikSeeder::class,
            YayasanAkunSeeder::class,
        ]);
    }
}
