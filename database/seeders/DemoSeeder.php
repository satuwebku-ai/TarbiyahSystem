<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAbsensiSeeder;
use Database\Seeders\Demo\DemoAkademikSeeder;
use Database\Seeders\Demo\DemoAkunSeeder;
use Database\Seeders\Demo\DemoDataset;
use Database\Seeders\Demo\DemoKepegawaianSeeder;
use Database\Seeders\Demo\DemoProgramSeeder;
use Database\Seeders\Demo\DemoSekolahSeeder;
use Database\Seeders\Demo\DemoStrukturSeeder;
use Illuminate\Database\Seeder;

/**
 * Data demo lengkap (fiktif) untuk tombol "Akun Demo" di halaman login:
 *   php artisan db:seed --class=DemoSeeder
 *
 *   Struktur   : 1 Pengurus Besar, 2 KOMWIL, 4 KOMDA, 12 sekolah (SD/SMP/SMA tiap KOMDA), Majelis Kelembagaan
 *   Orang      : pengurus, guru, tenaga administrasi, 360 pelajar beserta wali dan kelas
 *   Akun       : superadmin, pb, komwil.*, komda.*, kepsek.* (panel); kepsek.* dan guru.* (portal pegawai); NIS 1234567890 (pelajar)
 *   Aktivitas  : absensi pegawai dan siswa 20 hari kerja terakhir, mesin fingerprint, pengajuan koreksi,
 *                program kerja dengan seluruh status persetujuan, kegiatan, anggaran, dan laporan
 *
 * Aman diulang (tidak menggandakan data). Dilewati otomatis di production.
 * Urutan tahap penting, tetapi tiap tahap juga bisa dijalankan sendiri:
 *   php artisan db:seed --class="Database\Seeders\Demo\DemoAbsensiSeeder"
 */
class DemoSeeder extends Seeder
{
    use DemoDataset;

    public function run(): void
    {
        if (! $this->guardProduction()) {
            return;
        }

        $this->call([
            RbacSeeder::class,
            SchoolLevelSeeder::class,
            EmployeeTypeSeeder::class,
            OrganizationTypeSeeder::class,
            DemoStrukturSeeder::class,
            DemoSekolahSeeder::class,
            DemoKepegawaianSeeder::class,
            DemoAkademikSeeder::class,
            DemoAkunSeeder::class,
            PelajarSeeder::class,
            DemoAbsensiSeeder::class,
            DemoProgramSeeder::class,
            MajelisSeeder::class,
        ]);
    }
}
