<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;

class PermissionsSync extends Command
{
    protected $signature = 'permissions:sync {--prune : Hapus permission di database yang sudah tidak ada di config/permissions.php}';

    protected $description = 'Sinkronkan config/permissions.php ke tabel permissions';

    public function handle(): int
    {
        $keep = [];

        foreach (config('permissions') as $guard => $groups) {
            foreach ($groups as $group => $items) {
                foreach ($items as $name => $label) {
                    Permission::updateOrCreate(
                        ['name' => $name, 'guard_name' => $guard],
                        ['label' => $label, 'group' => $group],
                    );
                    $keep[] = "$guard|$name";
                }
            }
        }

        if ($this->option('prune')) {
            $stale = Permission::all()->reject(fn ($p) => in_array("{$p->guard_name}|{$p->name}", $keep, true));
            $stale->each->delete();
            $this->warn("Dihapus: {$stale->count()} permission usang.");
        }

        $this->info('Permission tersinkron: ' . count($keep));

        return self::SUCCESS;
    }
}
