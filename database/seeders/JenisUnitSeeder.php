<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnitType;
use Illuminate\Database\Seeder;

/**
 * 6. Jenis unit organisasi. Data referensi: aman untuk production dan dijalankan berulang
 * (jenis yang sudah ada tidak ditimpa).
 */
class JenisUnitSeeder extends Seeder
{
    public const TYPES = [
        'pengurus-besar' => ['Pengurus Besar', 'Pimpinan tertinggi yayasan.'],
        'majelis' => ['Majelis', 'Majelis di bawah Pengurus Besar.'],
        'komwil' => ['Komisaris Wilayah (KOMWIL)', 'Perwakilan yayasan setingkat provinsi.'],
        'komda' => ['Komisaris Daerah (KOMDA)', 'Perwakilan yayasan setingkat kabupaten/kota.'],
        'sekolah' => ['Sekolah', 'Satuan pendidikan (SD, SMP, SMA).'],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $slug => [$name, $description]) {
            OrganizationalUnitType::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_active' => true]);
        }
    }
}
