<?php

namespace Database\Seeders;

use App\Models\OrganizationalUnitType;
use Illuminate\Database\Seeder;

/**
 * Tipe unit organisasi (Pengurus Besar, KOMWIL, KOMDA, Sekolah). Hanya tipe, tidak membuat unit,
 * jadi aman untuk production dan dijalankan berulang.
 */
class OrganizationTypeSeeder extends Seeder
{
    public const TYPES = [
        'pengurus-besar' => ['Pengurus Besar', 'Pimpinan tertinggi yayasan.'],
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
