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
            PelajarSeeder::class, // pelajar demo (local saja)
        ]);
    }
}
