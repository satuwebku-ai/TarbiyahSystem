<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\StudentAccount;
use Illuminate\Database\Seeder;

/** Akun login pelajar demo (tabel student_accounts, guard "pelajar", masuk memakai NIS). */
class PelajarSeeder extends Seeder
{
    public function run(): void
    {
        // Password demo tertulis di kode: jangan pernah membuat akun ini di produksi.
        if (app()->environment('production')) {
            $this->command?->warn('PelajarSeeder dilewati di production (akun demo dengan password tertulis di kode).');

            return;
        }

        $account = StudentAccount::firstOrCreate(
            ['nis' => '1234567890'],
            ['name' => 'Pelajar Demo', 'email' => 'pelajar.demo@example.test', 'password' => 'Pelajar#2026Demo', 'is_active' => true],
        );

        if ($role = Role::where('guard_name', 'pelajar')->where('name', 'pelajar')->first()) {
            $account->syncRoles([$role->id]);
        }
    }
}