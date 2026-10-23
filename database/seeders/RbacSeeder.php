<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/** Permission + role bawaan (aman dijalankan berulang). */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permissions:sync');

        Role::updateOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'admin'],
            ['label' => 'Super Admin', 'description' => 'Akses penuh ke seluruh fitur', 'is_system' => true],
        );

        $staff = Role::updateOrCreate(
            ['name' => 'staf', 'guard_name' => 'admin'],
            ['label' => 'Staf', 'description' => 'Hanya melihat dashboard', 'is_system' => false],
        );
        $staff->permissions()->syncWithoutDetaching(
            Permission::where('guard_name', 'admin')->where('name', 'dashboard.view')->pluck('id')
        );

        $pelajar = Role::updateOrCreate(
            ['name' => 'pelajar', 'guard_name' => 'pelajar'],
            ['label' => 'Pelajar', 'description' => 'Pelajar standar', 'is_system' => true],
        );
        $pelajar->permissions()->syncWithoutDetaching(
            Permission::where('guard_name', 'pelajar')->pluck('id')
        );
    }
}
