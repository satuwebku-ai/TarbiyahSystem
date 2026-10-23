<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;

/**
 * Urutan baku pengisian struktur organisasi:
 * 1) Jenis unit -> 2) Unit -> 3) Jabatan -> 4) Penugasan.
 * Tiap langkah baru terbuka setelah langkah sebelumnya terisi.
 */
class OrganizationSetup
{
    public function __construct(private OrganizationAccess $access)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function steps(Admin $admin): array
    {
        $visible = $this->access->visibleUnitIds($admin);
        $scoped = fn ($query, string $column) => $visible === null ? $query : $query->whereIn($column, $visible);

        $counts = [
            'types' => OrganizationalUnitType::where('is_active', true)->count(),
            'units' => $scoped(OrganizationalUnit::where('status', 'active'), 'id')->count(),
            'positions' => $scoped(OrganizationalPosition::where('is_active', true), 'organizational_unit_id')->count(),
            'assignments' => $scoped(OrganizationalAssignment::where('status', 'active'), 'organizational_unit_id')->count(),
        ];

        $definitions = [
            ['key' => 'types', 'label' => 'Jenis unit', 'hint' => 'Contoh: Yayasan, Sekolah, Bagian', 'route' => 'admin.organization.unit-types.create', 'list' => 'admin.organization.unit-types.index', 'ability' => 'organization.types.manage', 'needs' => null],
            ['key' => 'units', 'label' => 'Unit', 'hint' => 'Susun induk dan turunannya', 'route' => 'admin.organization.units.create', 'list' => 'admin.organization.units.index', 'ability' => 'organization.units.manage', 'needs' => 'Buat jenis unit dulu.'],
            ['key' => 'positions', 'label' => 'Jabatan', 'hint' => 'Jabatan pada tiap unit', 'route' => 'admin.organization.positions.create', 'list' => 'admin.organization.positions.index', 'ability' => 'organization.positions.manage', 'needs' => 'Buat unit dulu.'],
            ['key' => 'assignments', 'label' => 'Penugasan', 'hint' => 'Siapa memegang jabatan', 'route' => 'admin.organization.assignments.create', 'list' => 'admin.organization.assignments.index', 'ability' => 'organization.assignments.manage', 'needs' => 'Buat jabatan dulu.'],
        ];

        $steps = [];
        $currentFound = false;
        foreach ($definitions as $i => $def) {
            $previousKey = $definitions[$i - 1]['key'] ?? null;
            $locked = $previousKey !== null && $counts[$previousKey] === 0;
            $done = ! $locked && $counts[$def['key']] > 0;
            $isCurrent = ! $locked && ! $done && ! $currentFound;
            $currentFound = $currentFound || $isCurrent;

            $steps[] = $def + [
                'number' => $i + 1,
                'count' => $counts[$def['key']],
                'state' => $locked ? 'locked' : ($done ? 'done' : 'current'),
                'is_next' => $isCurrent,
            ];
        }

        return $steps;
    }
}
