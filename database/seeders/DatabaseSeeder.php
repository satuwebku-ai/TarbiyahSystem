<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,              // permission + role
            SchoolLevelSeeder::class,       // jenjang SD/SMP/SMA
            OrganizationTypeSeeder::class,  // tipe unit organisasi
            AdminSeeder::class,             // Super Admin (dari .env)
        ]);

        // Data demo (password tertulis di kode): bukan untuk production.
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
