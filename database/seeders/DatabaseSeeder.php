<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,    // permission + role
            AdminSeeder::class,   // Super Admin (dari .env)
        ]);

        // Akun demo (password tertulis di kode): bukan untuk production.
        if (! app()->environment('production')) {
            $this->call(PelajarSeeder::class);
        }
    }
}
