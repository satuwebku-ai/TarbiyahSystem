<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitHistory;
use App\Services\OrganizationAccess;
use App\Services\OrganizationHierarchy;
use Illuminate\Support\Collection;

class OrganizationDashboardController extends Controller
{
    public function __invoke(OrganizationAccess $access, OrganizationHierarchy $hierarchy)
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $visibleIds = $access->visibleUnitIds($admin);

        $unitQuery = OrganizationalUnit::query()->with('type')->orderBy('name');
        if ($visibleIds !== null) {
            $unitQuery->whereIn('id', $visibleIds);
        }

        $units = $unitQuery->get();
        $unitIds = $units->pluck('id');

        $historyQuery = OrganizationalUnitHistory::query()
            ->with(['unit:id,name,code', 'actor:id,name'])
            ->orderByDesc('created_at');
        if ($visibleIds !== null) {
            $historyQuery->whereIn('organizational_unit_id', $visibleIds);
        }

        $audit = $hierarchy->audit();
        [$addable, $rootTypes] = $hierarchy->addOptions($units, $admin->can('organization.units.manage'), $visibleIds === null);

        return view('admin.organization.index', [
            'addable' => $addable,
            'rootTypes' => $rootTypes,
            'parentChoices' => $hierarchy->parentChoices($units, $addable),
            'unitNames' => $units->pluck('name', 'id')->all(),
            'invalidUnits' => $units->whereIn('id', $audit['own'])->values(),
            'ownInvalidIds' => $audit['own'],
            'unitCount' => $units->count(),
            'activeUnitCount' => $units->where('status', 'active')->count(),
            'unitTypeCount' => $units->pluck('type_id')->unique()->count(),
            'positionCount' => OrganizationalPosition::whereIn('organizational_unit_id', $unitIds)->count(),
            'assignmentCount' => OrganizationalAssignment::whereIn('organizational_unit_id', $unitIds)
                ->where('status', 'active')
                ->count(),
            'tree' => $this->makeTree($units),
            'recentChanges' => $historyQuery->limit(8)->get(),
        ]);
    }

    private function makeTree(Collection $units): array
    {
        $byId = $units->keyBy('id');
        $children = $units->groupBy('parent_id');

        $build = function (OrganizationalUnit $unit) use (&$build, $children): array {
            return [
                'unit' => $unit,
                'children' => $children->get($unit->id, collect())
                    ->map(fn (OrganizationalUnit $child) => $build($child))
                    ->all(),
            ];
        };

        return $units
            ->filter(fn (OrganizationalUnit $unit) => ! $unit->parent_id || ! $byId->has($unit->parent_id))
            ->map(fn (OrganizationalUnit $unit) => $build($unit))
            ->values()
            ->all();
    }
}
