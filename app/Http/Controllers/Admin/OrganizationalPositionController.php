<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitHistory;
use App\Services\OrganizationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;

class OrganizationalPositionController extends Controller
{
    public function index(Request $request, OrganizationAccess $access): View|JsonResponse
    {
        $visibleIds = $access->visibleUnitIds(auth('admin')->user());

        if ($request->query('view') !== 'table') {
            return $this->treeIndex($request, $access, $visibleIds);
        }

        $query = OrganizationalPosition::query()
            ->with('unit:id,name,code')
            ->withCount('assignments')
            ->orderBy('name');

        if ($visibleIds !== null) {
            $query->whereIn('organizational_unit_id', $visibleIds);
        }
        if ($unitId = $request->integer('unit_id')) {
            $query->where('organizational_unit_id', $unitId);
        }

        if (Dt::wants($request)) {
            return DataTables::eloquent($query->reorder())
                ->editColumn('name', fn (OrganizationalPosition $p) => Dt::stack($p->name, $p->description))
                ->addColumn('unit_name', fn (OrganizationalPosition $p) => e($p->unit?->name).' <span class="small text-secondary">('.e($p->unit?->code).')</span>')
                ->editColumn('assignments_count', fn (OrganizationalPosition $p) => Dt::num($p->assignments_count))
                ->editColumn('is_active', fn (OrganizationalPosition $p) => Dt::badge($p->is_active))
                ->addColumn('action', fn (OrganizationalPosition $p) => view('admin.organization.positions._actions', ['position' => $p])->render())
                ->orderColumn('unit_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'organizational_units', 'organizational_unit_id', 'organizational_positions'))
                ->filterColumn('name', fn ($q, $kw) => $q->where(fn ($w) => $w->where('name', 'like', "%{$kw}%")->orWhere('description', 'like', "%{$kw}%")))
                ->filterColumn('unit_name', fn ($q, $kw) => Dt::filterRelated($q, 'organizational_unit_id', 'organizational_units', ['name', 'code'], $kw))
                ->rawColumns(['name', 'unit_name', 'is_active', 'action'])
                ->make(true);
        }

        return view('admin.organization.positions.index', [
            'view' => 'table',
            'units' => $this->availableUnits($access),
            'unitOptions' => $this->treeOptions($this->availableUnits($access)),
            'selectedUnitId' => $request->integer('unit_id') ?: null,
            'search' => '',
        ]);
    }

    /**
     * Bagan jabatan per unit. Akar bagan mengikuti cakupan akses pengguna yang login:
     * pengelola KOMWIL melihat KOMWIL dan turunannya, pengelola KOMDA melihat KOMDA dan
     * sekolahnya, sedangkan pimpinan sekolah hanya melihat sekolahnya (jabatan, guru, dan
     * tenaga kependidikan).
     */
    private function treeIndex(Request $request, OrganizationAccess $access, ?array $visibleIds): View
    {
        $admin = auth('admin')->user();
        $unitQuery = OrganizationalUnit::query()->with('type:id,name')->orderBy('name');
        if ($visibleIds !== null) {
            $unitQuery->whereIn('id', $visibleIds);
        }
        $visibleUnits = $unitQuery->get();
        $visibleById = $visibleUnits->keyBy('id');

        $unitOptions = $visibleUnits->map(function (OrganizationalUnit $unit) use ($visibleById) {
            $names = [];
            $cursor = $unit;
            for ($guard = 0; $cursor && $guard < 25; $guard++) {
                array_unshift($names, $cursor->name);
                $cursor = $cursor->parent_id ? $visibleById->get($cursor->parent_id) : null;
            }

            return ['id' => $unit->id, 'label' => implode(' › ', $names)];
        })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        $scopeRoots = $visibleIds === null
            ? []
            : $visibleUnits->filter(fn (OrganizationalUnit $unit) => ! $unit->parent_id || ! $visibleById->has($unit->parent_id))
                ->pluck('name')->values()->all();

        $selectedUnitId = $request->integer('unit_id') ?: null;
        $units = $visibleUnits;
        if ($selectedUnitId && $visibleById->has($selectedUnitId)) {
            $subtree = $access->withDescendants([$selectedUnitId]);
            $units = $visibleUnits->filter(fn (OrganizationalUnit $unit) => in_array($unit->id, $subtree, true))->values();
        } else {
            $selectedUnitId = null;
        }

        $unitIds = $units->pluck('id')->all();
        $today = today()->toDateString();
        $current = fn ($query) => $query
            ->whereDate('start_date', '<=', $today)
            ->where(fn ($inner) => $inner->whereNull('end_date')->orWhereDate('end_date', '>=', $today));

        $positions = OrganizationalPosition::query()
            ->whereIn('organizational_unit_id', $unitIds)
            ->with(['assignments' => fn ($query) => $current($query->where('status', 'active'))
                ->select('id', 'organizational_position_id', 'assignee_name')])
            ->orderBy('name')
            ->get();

        $employeeAssignments = EmployeeAssignment::query()
            ->whereIn('organizational_unit_id', $unitIds)
            ->whereHas('employee', fn ($query) => $query->where('status', 'active'))
            ->tap(fn ($query) => $current($query))
            ->with('employee:id,name,employee_type')
            ->get(['id', 'employee_id', 'organizational_unit_id', 'organizational_position_id']);

        // Nama orang per jabatan (penugasan struktural + pegawai), dan pegawai tanpa jabatan per unit.
        $positionPeople = [];
        foreach ($positions as $position) {
            $positionPeople[$position->id] = $position->assignments->pluck('assignee_name')->all();
        }
        $unitPeople = [];
        foreach ($employeeAssignments as $row) {
            if (! $row->employee) {
                continue;
            }
            if ($row->organizational_position_id) {
                $positionPeople[$row->organizational_position_id][] = $row->employee->name;
            } else {
                $unitPeople[$row->organizational_unit_id][$row->employee->employee_type][] = $row->employee->name;
            }
        }
        foreach ($positionPeople as $id => $names) {
            $positionPeople[$id] = array_values(array_unique($names));
        }

        $search = mb_strtolower(trim((string) $request->query('search')));
        $positionsByUnit = $positions->groupBy('organizational_unit_id');
        $display = [];
        $included = [];

        foreach ($units as $unit) {
            $unitMatch = $search === ''
                || str_contains(mb_strtolower($unit->name), $search)
                || str_contains(mb_strtolower($unit->code), $search);

            $rows = [];
            foreach ($positionsByUnit->get($unit->id, collect()) as $position) {
                $names = $positionPeople[$position->id] ?? [];
                $hit = $search !== '' && (str_contains(mb_strtolower($position->name), $search) || $this->anyContains($names, $search));
                if ($unitMatch || $hit) {
                    $rows[] = ['position' => $position, 'people' => $names, 'match' => $hit];
                }
            }

            $groups = [];
            foreach ($unitPeople[$unit->id] ?? [] as $type => $names) {
                $hit = $search !== '' && $this->anyContains($names, $search);
                if ($unitMatch || $hit) {
                    $groups[] = ['label' => Employee::typeOptions(false)[$type] ?? $type, 'names' => array_values(array_unique($names)), 'match' => $hit];
                }
            }

            $display[$unit->id] = ['positions' => $rows, 'groups' => $groups, 'match' => $search !== '' && $unitMatch];

            if ($unitMatch || $rows !== [] || $groups !== []) {
                $byId = $units->keyBy('id');
                $cursor = $unit;
                for ($guard = 0; $cursor && $guard < 25; $guard++) {
                    $included[$cursor->id] = true;
                    $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
                }
            }
        }

        $shown = $units->filter(fn (OrganizationalUnit $unit) => isset($included[$unit->id]))->values();

        return view('admin.organization.positions.index', [
            'view' => 'tree',
            'tree' => $this->makeTree($shown, $display),
            'search' => $search,
            'filtered' => $search !== '',
            'selectedUnitId' => $selectedUnitId,
            'unitOptions' => $unitOptions,
            'scopeRoots' => $scopeRoots,
            'canManagePositions' => $admin->can('organization.positions.manage'),
            'canManageAssignments' => $admin->can('organization.assignments.manage'),
            'positionCount' => $positions->count(),
        ]);
    }

    /** @param  string[]  $names */
    private function anyContains(array $names, string $needle): bool
    {
        foreach ($names as $name) {
            if (str_contains(mb_strtolower($name), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, OrganizationalUnit>  $units
     * @param  array<int, array<string, mixed>>  $display
     * @return array<int, array<string, mixed>>
     */
    private function makeTree(Collection $units, array $display): array
    {
        $byId = $units->keyBy('id');
        $children = $units->groupBy('parent_id');

        $build = function (OrganizationalUnit $unit) use (&$build, $children, $display): array {
            return [
                'unit' => $unit,
                'positions' => $display[$unit->id]['positions'] ?? [],
                'groups' => $display[$unit->id]['groups'] ?? [],
                'match' => $display[$unit->id]['match'] ?? false,
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

    public function create(Request $request, OrganizationAccess $access): View
    {
        return view('admin.organization.positions.form', [
            'position' => new OrganizationalPosition([
                'is_active' => true, 'capacity' => 1,
                'organizational_unit_id' => $request->integer('unit_id') ?: null,
            ]),
            'units' => $this->availableUnits($access),
            'unitOptions' => $this->treeOptions($this->availableUnits($access)),
        ]);
    }

    public function store(Request $request, OrganizationAccess $access): RedirectResponse
    {
        $data = $this->validated($request);
        $access->assertCanAccess(auth('admin')->user(), (int) $data['organizational_unit_id']);
        $position = OrganizationalPosition::create($data);
        $this->recordHistory($position->organizational_unit_id, 'position_created', "Jabatan {$position->name} ditambahkan.");

        if ($request->input('then') === 'another') {
            return redirect()->route('admin.organization.positions.create', ['unit_id' => $position->organizational_unit_id])
                ->with('success', "Jabatan {$position->name} ditambahkan. Silakan tambah jabatan lain.");
        }

        $unitHasAssignments = \App\Models\OrganizationalAssignment::query()
            ->where('organizational_unit_id', $position->organizational_unit_id)
            ->exists();
        if (! $unitHasAssignments && auth('admin')->user()->can('organization.assignments.manage')) {
            return redirect()->route('admin.organization.assignments.create', [
                'unit_id' => $position->organizational_unit_id,
                'position_id' => $position->id,
            ])->with('success', 'Jabatan berhasil ditambahkan. Lanjut: tugaskan seseorang pada jabatan ini.');
        }

        return redirect()->route('admin.organization.positions.index')->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function edit(OrganizationalPosition $position, OrganizationAccess $access): View
    {
        $access->assertCanAccess(auth('admin')->user(), $position->organizational_unit_id);

        return view('admin.organization.positions.form', [
            'position' => $position,
            'units' => $this->availableUnits($access),
            'unitOptions' => $this->treeOptions($this->availableUnits($access)),
        ]);
    }

    public function update(Request $request, OrganizationalPosition $position, OrganizationAccess $access): RedirectResponse
    {
        $access->assertCanAccess(auth('admin')->user(), $position->organizational_unit_id);
        $data = $this->validated($request, $position);
        $access->assertCanAccess(auth('admin')->user(), (int) $data['organizational_unit_id']);
        $oldUnitId = $position->organizational_unit_id;
        $position->update($data);
        $this->recordHistory($oldUnitId, 'position_updated', "Jabatan {$position->name} diperbarui.");
        if ($oldUnitId !== $position->organizational_unit_id) {
            $this->recordHistory($position->organizational_unit_id, 'position_moved', "Jabatan {$position->name} dipindahkan ke unit ini.");
        }

        return redirect()->route('admin.organization.positions.index')->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function toggle(OrganizationalPosition $position, OrganizationAccess $access): RedirectResponse
    {
        $access->assertCanAccess(auth('admin')->user(), $position->organizational_unit_id);
        $position->update(['is_active' => ! $position->is_active]);
        $this->recordHistory(
            $position->organizational_unit_id,
            'position_status_changed',
            $position->is_active ? "Jabatan {$position->name} diaktifkan." : "Jabatan {$position->name} dinonaktifkan."
        );

        return back()->with('success', $position->is_active ? 'Jabatan diaktifkan.' : 'Jabatan dinonaktifkan.');
    }

    private function validated(Request $request, ?OrganizationalPosition $position = null): array
    {
        $data = $request->validate([
            'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'unlimited' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        // Kosong atau "tanpa batas" = tidak dibatasi; selain itu jumlah pemegang maksimum.
        $data['capacity'] = $request->boolean('unlimited') || ($data['capacity'] ?? null) === null ? null : (int) $data['capacity'];
        unset($data['unlimited']);

        $unique = Rule::unique('organizational_positions', 'name')
            ->where('organizational_unit_id', $data['organizational_unit_id'])
            ->ignore($position?->id);
        validator(['name' => $data['name']], ['name' => [$unique]])->validate();

        return $data;
    }

    /**
     * Pilihan unit berurutan menurut pohon (induk lalu anak) dengan indentasi, supaya jelas
     * posisinya: Pengurus Besar > Majelis Kelembagaan > Majelis Ekonomi.
     *
     * @return array<int, array{id:int,label:string,path:string,code:string,type:string,depth:int}>
     */
    private function treeOptions($units): array
    {
        $byId = $units->keyBy('id');
        $children = $units->groupBy(fn ($u) => $u->parent_id && $byId->has($u->parent_id) ? $u->parent_id : 0);
        $out = [];
        $walk = function (int $parent, int $depth, array $trail) use (&$walk, $children, &$out): void {
            foreach ($children->get($parent, collect())->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE) as $unit) {
                $path = [...$trail, $unit->name];
                $out[] = [
                    'id' => $unit->id,
                    'label' => str_repeat("\u{00A0}\u{00A0}\u{00A0}", $depth).($depth ? '└ ' : '').$unit->name,
                    'path' => implode(' › ', $path),
                    'code' => $unit->code,
                    'type' => $unit->type?->name ?? '',
                    'depth' => $depth,
                ];
                $walk($unit->id, $depth + 1, $path);
            }
        };
        $walk(0, 0, []);

        return $out;
    }

    private function availableUnits(OrganizationAccess $access)
    {
        $visibleIds = $access->visibleUnitIds(auth('admin')->user());
        $query = OrganizationalUnit::query()->with('type:id,name')->orderBy('name')->where('status', 'active');

        if ($visibleIds !== null) {
            $query->whereIn('id', $visibleIds);
        }

        return $query->get();
    }

    private function recordHistory(int $unitId, string $action, string $summary): void
    {
        OrganizationalUnitHistory::create([
            'organizational_unit_id' => $unitId,
            'actor_admin_id' => auth('admin')->id(),
            'action' => $action,
            'summary' => $summary,
            'created_at' => now(),
        ]);
    }
}
