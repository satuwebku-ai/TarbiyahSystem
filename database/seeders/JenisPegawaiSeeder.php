<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use Illuminate\Database\Seeder;

/**
 * 5. Jenis pegawai: Guru, Tenaga Kependidikan, Pegawai PB. Data referensi: aman untuk production
 * dan dijalankan berulang (nama atau status yang diubah pengelola tidak ditimpa).
 */
class JenisPegawaiSeeder extends Seeder
{
    /** kode (maks. 20 karakter) => [nama, keterangan] */
    public const TYPES = [
        'teacher' => ['Guru', 'Pendidik di satuan pendidikan (SD, SMP, SMA), termasuk kepala sekolah.'],
        'staff' => ['Tenaga kependidikan', 'Tenaga administrasi dan penunjang di satuan pendidikan.'],
        'pb' => ['Pegawai PB', 'Pengurus dan staf sekretariat di tingkat Pengurus Besar, majelis, KOMWIL, dan KOMDA.'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $code => [$name, $description]) {
            EmployeeType::firstOrCreate(['code' => $code], ['name' => $name, 'description' => $description, 'is_active' => true]);
        }
    }
}
