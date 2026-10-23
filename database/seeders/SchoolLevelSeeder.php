<?php

namespace Database\Seeders;

use App\Models\SchoolLevel;
use Illuminate\Database\Seeder;

/** Jenjang pendidikan SD/SMP/SMA. Data referensi: aman untuk production dan dijalankan berulang. */
class SchoolLevelSeeder extends Seeder
{
    public const LEVELS = [
        'sd' => ['SD', 1],
        'smp' => ['SMP', 2],
        'sma' => ['SMA', 3],
    ];

    public function run(): void
    {
        foreach (self::LEVELS as $slug => [$name, $order]) {
            SchoolLevel::firstOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $order, 'is_active' => true]);
        }
    }
}
