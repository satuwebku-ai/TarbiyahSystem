<?php

namespace Database\Seeders;

use App\Models\EmployeeType;
use Illuminate\Database\Seeder;

/**
 * Jenis pegawai bawaan: Guru, Tenaga Kependidikan, dan Pegawai PB (pengurus/sekretariat yayasan).
 * Data referensi: aman untuk production dan dijalankan berulang. Jenis yang sudah ada (termasuk yang
 * namanya diubah atau dinonaktifkan pengelola) tidak ditimpa. Jenis tambahan dikelola lewat menu Jenis Pegawai.
 */
class EmployeeTypeSeeder extends Seeder
{
    /** kode (maks. 20 karakter) => [nama, keterangan] */
    public const TYPES = [
        'teacher' => ['Guru', 'Pendidik di satuan pendidikan (SD, SMP, SMA), termasuk kepala sekolah.'],
        'staff' => ['Tenaga kependidikan', 'Tenaga administrasi dan penunjang di satuan pendidikan.'],
        'pb' => ['Pegawai PB', 'Pengurus dan staf sekretariat di tingkat Pengurus Besar, KOMWIL, dan KOMDA.'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $code => [$name, $description]) {
            EmployeeType::firstOrCreate(['code' => $code], ['name' => $name, 'description' => $description, 'is_active' => true]);
        }
    }
}
