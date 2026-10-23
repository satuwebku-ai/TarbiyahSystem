<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnit;
use App\Models\UserUnitAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrganizationAccessController extends Controller
{
    public function index(Request $request): View
    {
        $admins = Admin::query()->with('roles')->orderBy('name')->get(['id', 'name', 'username', 'email', 'is_active']);
        $selected = $admins->firstWhere('id', $request->integer('admin_id')) ?? $admins->first();
        $units = OrganizationalUnit::query()->with(['parent:id,name', 'type:id,name'])->orderBy('name')->get();
        $unitIds = $selected
            ? UserUnitAccess::query()
                ->where('model_type', Admin::class)
                ->where('model_id', $selected->id)
                ->pluck('organizational_unit_id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];
        // Urutan pohon (induk lalu turunannya) + daftar turunan tiap unit untuk pratinjau cakupan di halaman.
        $byParent = $units->groupBy(fn (OrganizationalUnit $unit) => $unit->parent_id ?: 0);
        $knownIds = $units->pluck('id')->flip();
        $ordered = [];
        $descendants = [];
        $walk = function (OrganizationalUnit $unit, int $depth, array $trail) use (&$walk, &$ordered, &$descendants, $byParent): array {
            $unit->setAttribute('tree_depth', $depth);
            $unit->setAttribute('tree_path', implode(' › ', $trail));
            $ordered[] = $unit;
            $below = [];
            foreach ($byParent->get($unit->id, collect())->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE) as $child) {
                $below = [...$below, $child->id, ...$walk($child, $depth + 1, [...$trail, $unit->name])];
            }
            $descendants[$unit->id] = $below;

            return $below;
        };
        $roots = $units->filter(fn (OrganizationalUnit $unit) => ! $unit->parent_id || ! $knownIds->has($unit->parent_id))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($roots as $root) {
            $walk($root, 0, []);
        }
        $units = collect($ordered);

        $unrestricted = $selected && ($selected->isSuperAdmin() || $selected->hasPermission('organization.access.all'));

        return view('admin.organization.access.index', compact('admins', 'selected', 'units', 'unitIds', 'descendants', 'unrestricted'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admin_id' => ['required', 'integer', Rule::exists('admins', 'id')],
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['integer', Rule::exists('organizational_units', 'id')],
        ]);

        $admin = Admin::query()->findOrFail($data['admin_id']);
        $unitIds = array_values(array_unique(array_map('intval', $data['unit_ids'] ?? [])));

        DB::transaction(function () use ($admin, $unitIds) {
            UserUnitAccess::query()
                ->where('model_type', Admin::class)
                ->where('model_id', $admin->id)
                ->delete();

            foreach ($unitIds as $unitId) {
                UserUnitAccess::create([
                    'organizational_unit_id' => $unitId,
                    'model_type' => Admin::class,
                    'model_id' => $admin->id,
                    'created_at' => now(),
                ]);
            }

            AdminAuditLog::record('organization.access.updated', auth('admin')->user(), $admin, [
                'organizational_unit_ids' => $unitIds,
            ]);
        });

        return redirect()->route('admin.organization.access.index', ['admin_id' => $admin->id])
            ->with('success', 'Cakupan unit untuk akun pengelola berhasil diperbarui.');
    }
}
