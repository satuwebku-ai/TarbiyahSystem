<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Semua akun untuk tombol "Akun Demo" di halaman login:
 *   php artisan db:seed --class=DemoSeeder
 *
 *   Pengelola : superadmin, pb, komwil.*, komda.*, kepsek.*   (YayasanSeeder)
 *   Pegawai   : kepala sekolah yang terhubung ke data pegawai (YayasanSeeder)
 *   Pelajar   : NIS 1234567890                                (PelajarSeeder)
 *
 * Aman diulang. YayasanSeeder dilewati otomatis di produksi.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            YayasanSeeder::class,   // struktur, sekolah, pegawai, akun pengelola + pegawai
            PelajarSeeder::class,   // pelajar demo
        ]);
    }
}
