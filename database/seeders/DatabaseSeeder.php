<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Urutan penting (tiap seeder juga bisa dijalankan sendiri; prasyaratnya dipanggil otomatis):
 *   php artisan db:seed                         -> semuanya (data contoh dilewati di production)
 *   php artisan db:seed --class=SiswaSeeder     -> hanya data siswa (beserta prasyaratnya)
 *
 * Data referensi (aman untuk production, dijalankan berulang): Rbac, Super Admin, Jenis Unit, Jenis Pegawai, Jenjang.
 * Data contoh fiktif (dilewati di production): Unit, Jabatan, Sekolah, Pegawai, Penugasan, Siswa, Akun Contoh.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,           // permission + role
            SuperAdminSeeder::class,     // 1. akun Super Admin
            JenisUnitSeeder::class,      // 6. jenis unit organisasi
            JenisPegawaiSeeder::class,   // 5. jenis pegawai
            JenjangSekolahSeeder::class, //    jenjang SD/SMP/SMA
            UnitSeeder::class,           // 7. unit organisasi
            JabatanSeeder::class,        // 8. jabatan
            SekolahSeeder::class,        // 4. data sekolah (+ tahun ajaran, kelas)
            PegawaiSeeder::class,        // 2. data pegawai
            PenugasanSeeder::class,      // 9. penugasan
            SiswaSeeder::class,          // 3. data siswa
            AkunContohSeeder::class,     //    1 akun login contoh: pegawai dan pelajar
        ]);
    }
}
