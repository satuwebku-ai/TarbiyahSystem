<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\OrganizationalUnit;
use App\Models\UserUnitAccess;

class OrganizationAccess
{
    /**
     * Return null for unrestricted administrators, otherwise the assigned units
     * and all descendants. An empty array deliberately means no unit access.
     */
    public function visibleUnitIds(Admin $admin): ?array
    {
        if ($admin->isSuperAdmin() || $admin->hasPermission('organization.access.all')) {
            return null;
        }

        $visible = UserUnitAccess::query()
            ->where('model_type', Admin::class)
            ->where('model_id', $admin->getKey())
            ->pluck('organizational_unit_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->withDescendants($visible);
    }

    /**
     * Return the supplied units and every descendant, without changing the
     * caller's authorization scope. Callers must intersect with visibleUnitIds.
     */
    public function withDescendants(array $unitIds): array
    {
        $visible = array_values(array_unique(array_map('intval', $unitIds)));
        $frontier = $visible;
        while ($frontier !== []) {
            $children = OrganizationalUnit::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $frontier = array_values(array_diff($children, $visible));
            $visible = array_values(array_unique([...$visible, ...$frontier]));
        }

        return $visible;
    }

    public function canAccess(Admin $admin, int $unitId): bool
    {
        $visible = $this->visibleUnitIds($admin);

        return $visible === null || in_array($unitId, $visible, true);
    }

    public function assertCanAccess(Admin $admin, int $unitId): void
    {
        abort_unless($this->canAccess($admin, $unitId), 403);
    }
}
