<?php

namespace Database\Seeders;

use Database\Seeders\Contoh\ContohPegawaiSeeder;
use Database\Seeders\Contoh\ContohSekolahSeeder;
use Database\Seeders\Contoh\ContohSiswaSeeder;
use Database\Seeders\Contoh\ContohUnitOrganisasiSeeder;
use Illuminate\Database\Seeder;

/**
 * Seluruh data contoh kecil sekaligus (dilewati di production, aman diulang):
 *   php artisan db:seed --class=ContohDataSeeder
 * Atau satu per satu:
 *   php artisan db:seed --class="Database\Seeders\Contoh\ContohUnitOrganisasiSeeder"
 *   php artisan db:seed --class="Database\Seeders\Contoh\ContohSekolahSeeder"
 *   php artisan db:seed --class="Database\Seeders\Contoh\ContohPegawaiSeeder"
 *   php artisan db:seed --class="Database\Seeders\Contoh\ContohSiswaSeeder"
 */
class ContohDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SchoolLevelSeeder::class,
            EmployeeTypeSeeder::class,
            OrganizationTypeSeeder::class,
            ContohUnitOrganisasiSeeder::class,
            ContohSekolahSeeder::class,
            ContohPegawaiSeeder::class,
            ContohSiswaSeeder::class,
        ]);
    }
}
