<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitHistory;
use App\Models\OrganizationalUnitType;
use App\Services\OrganizationAccess;
use App\Services\OrganizationHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;

class OrganizationalUnitController extends Controller
{
    public function __construct(private readonly OrganizationHierarchy $hierarchy)
    {
    }

    public function index(Request $request, OrganizationAccess $access): View|JsonResponse|string
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $visibleIds = $access->visibleUnitIds($admin);
        $types = OrganizationalUnitType::query()->where('is_active', true)->orderBy('name')->get();
        $view = $request->query('view') === 'table' ? 'table' : 'tree';

        if ($view === 'tree') {
            return $this->treeIndex($request, $admin, $visibleIds, $types);
        }

        $query = OrganizationalUnit::query()
            ->with(['type:id,name', 'parent:id,name,code'])
            ->withCount(['children', 'positions'])
            ->orderBy('name');

        $this->applyScope($query, $visibleIds);

        if ($typeId = $request->integer('type_id')) {
            $query->where('type_id', $typeId);
        }
        if (in_array($request->query('status'), ['active', 'inactive'], true)) {
            $query->where('status', $request->query('status'));
        }

        if (Dt::wants($request)) {
            $canManage = $admin->can('organization.units.manage');

            return DataTables::eloquent($query->reorder())
                ->editColumn('name', fn (OrganizationalUnit $u) => '<div class="fw-semibold">'.e($u->name).'</div><div class="small text-secondary"><code>'.e($u->code).'</code></div>')
                ->addColumn('type_name', fn (OrganizationalUnit $u) => e($u->type?->name ?? '—'))
                ->addColumn('parent_name', fn (OrganizationalUnit $u) => e($u->parent?->name ?? 'Unit induk'))
                ->editColumn('children_count', fn (OrganizationalUnit $u) => Dt::num($u->children_count))
                ->editColumn('positions_count', fn (OrganizationalUnit $u) => Dt::num($u->positions_count))
                ->editColumn('status', fn (OrganizationalUnit $u) => Dt::badge($u->status === 'active'))
                ->addColumn('action', fn (OrganizationalUnit $u) => view('admin.organization.units._actions', ['unit' => $u, 'canManage' => $canManage])->render())
                ->orderColumn('type_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'organizational_unit_types', 'type_id', 'organizational_units'))
                ->orderColumn('parent_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'organizational_units', 'parent_id', 'organizational_units'))
                ->filterColumn('name', fn ($q, $kw) => $q->where(fn ($w) => $w->where('name', 'like', "%{$kw}%")->orWhere('code', 'like', "%{$kw}%")))
                ->filterColumn('type_name', fn ($q, $kw) => Dt::filterRelated($q, 'type_id', 'organizational_unit_types', ['name'], $kw))
                ->filterColumn('parent_name', fn ($q, $kw) => Dt::filterRelated($q, 'parent_id', 'organizational_units', ['name', 'code'], $kw))
                ->rawColumns(['name', 'type_name', 'parent_name', 'status', 'action'])
                ->make(true);
        }

        // Tombol "Tambah unit" di tampilan tabel memakai wizard yang sama dengan bagan.
        $scoped = OrganizationalUnit::query()->with('type:id,slug,name')->orderBy('name');
        $this->applyScope($scoped, $visibleIds);
        $all = $scoped->get();
        [$addable, $rootTypes] = $this->hierarchy->addOptions(
            $all,
            $admin->can('organization.units.manage'),
            $visibleIds === null,
        );

        return view('admin.organization.units.index', [
            'view' => 'table',
            'types' => $types,
            'filters' => $request->only(['search', 'type_id', 'status']),
            'addable' => $addable,
            'rootTypes' => $rootTypes,
            'parentChoices' => $this->hierarchy->parentChoices($all, $addable),
            'flow' => $this->hierarchy->flowSteps($all, $addable, $rootTypes),
            'unitNames' => $all->pluck('name', 'id')->all(),
        ]);
    }

    /**
     * Tampilan bagan: unit yang cocok dengan filter ditampilkan bersama seluruh induknya
     * agar posisinya dalam struktur tetap terlihat.
     */
    private function treeIndex(Request $request, Admin $admin, ?array $visibleIds, Collection $types): View|string
    {
        $query = OrganizationalUnit::query()
            ->with('type:id,slug,name')
            ->withCount(['children', 'positions'])
            ->orderBy('name');
        $this->applyScope($query, $visibleIds);

        $all = $query->get();
        $byId = $all->keyBy('id');

        $search = mb_strtolower(trim((string) $request->query('search')));
        $typeId = $request->integer('type_id');
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;
        $filtered = $search !== '' || $typeId > 0 || $status !== null;

        $matchedIds = [];
        $included = $all;

        if ($filtered) {
            $matches = $all->filter(function (OrganizationalUnit $unit) use ($search, $typeId, $status) {
                if ($search !== '' && ! str_contains(mb_strtolower($unit->name), $search) && ! str_contains(mb_strtolower($unit->code), $search)) {
                    return false;
                }
                if ($typeId > 0 && (int) $unit->type_id !== $typeId) {
                    return false;
                }

                return $status === null || $unit->status === $status;
            });

            $matchedIds = $matches->pluck('id')->all();
            $keep = [];
            foreach ($matches as $unit) {
                $cursor = $unit;
                for ($guard = 0; $cursor && $guard < 25; $guard++) {
                    $keep[$cursor->id] = true;
                    $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
                }
            }
            $included = $all->filter(fn (OrganizationalUnit $unit) => isset($keep[$unit->id]));
        }

        [$addable, $rootTypes] = $this->hierarchy->addOptions(
            $all,
            $admin->can('organization.units.manage'),
            $visibleIds === null,
        );

        return view('admin.organization.units.index', [
            'view' => 'tree',
            'types' => $types,
            'filters' => $request->only(['search', 'type_id', 'status']),
            'tree' => $this->makeTree($included),
            'filtered' => $filtered,
            'matchedIds' => $matchedIds,
            'totalCount' => $all->count(),
            'shownCount' => $included->count(),
            'addable' => $addable,
            'rootTypes' => $rootTypes,
            'parentChoices' => $this->hierarchy->parentChoices($all, $addable),
            'flow' => $this->hierarchy->flowSteps($all, $addable, $rootTypes),
            'unitNames' => $all->pluck('name', 'id')->all(),
            'ownInvalidIds' => $this->hierarchy->audit()['own'],
        ])->fragmentIf($request->ajax(), 'table'); // pencarian AJAX: hanya bagian bagan yang dikirim
    }

    /** @return array<int, array{unit: OrganizationalUnit, children: array}> */
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

    public function create(Request $request, OrganizationAccess $access): View
    {
        $admin = auth('admin')->user();
        $visibleIds = $access->visibleUnitIds($admin);

        return view('admin.organization.units.form', [
            'unit' => new OrganizationalUnit([
                'status' => 'active',
                'type_id' => $request->integer('type_id') ?: null,
                'parent_id' => $request->integer('parent_id') ?: null,
            ]),
            'types' => $this->selectableTypes(),
            'parents' => $this->parentOptions($access),
            'levels' => $this->hierarchy->formConfig(),
            'canCreateRoot' => $visibleIds === null,
        ]);
    }

    public function store(Request $request, OrganizationAccess $access): RedirectResponse
    {
        $data = $this->validated($request);
        $data['parent_id'] = $data['parent_id'] ?? null;
        $this->hierarchy->validateParent(OrganizationalUnitType::query()->findOrFail($data['type_id']), $data['parent_id']);
        $this->assertParentAccess($data['parent_id'] !== null ? (int) $data['parent_id'] : null, $access);

        $unit = DB::transaction(function () use ($data) {
            $unit = OrganizationalUnit::create($data);
            $this->recordHistory($unit, 'created', 'Unit organisasi dibuat.', ['after' => $unit->only([
                'type_id', 'parent_id', 'code', 'name', 'description', 'address', 'status',
            ])]);

            AdminAuditLog::record('organization.unit_created', auth('admin')->user(), $unit);

            return $unit;
        });

        if ($request->input('return_to') === 'units') {
            return redirect()->route('admin.organization.units.index')->with('success', "{$unit->name} berhasil ditambahkan.");
        }

        if ($request->input('return_to') === 'organization') {
            return redirect()->route('admin.organization.index')->with('success', "{$unit->name} berhasil ditambahkan ke bagan.");
        }

        return redirect()->route('admin.organization.units.show', $unit)->with('success', 'Unit organisasi berhasil ditambahkan.');
    }

    public function show(OrganizationalUnit $unit, OrganizationAccess $access): View
    {
        $access->assertCanAccess(auth('admin')->user(), $unit->id);
        $unit->load([
            'type:id,name',
            'parent:id,name,code',
            'children:id,type_id,parent_id,code,name,status',
            'positions' => fn ($query) => $query->orderBy('name'),
            'assignments' => fn ($query) => $query->with(['position:id,name', 'admin:id,name'])
                ->orderByDesc('start_date'),
            'histories' => fn ($query) => $query->with('actor:id,name')->orderByDesc('created_at')->limit(12),
        ]);

        return view('admin.organization.units.show', compact('unit'));
    }

    public function edit(OrganizationalUnit $unit, OrganizationAccess $access): View
    {
        $access->assertCanAccess(auth('admin')->user(), $unit->id);

        return view('admin.organization.units.form', [
            'unit' => $unit,
            'types' => $this->selectableTypes($unit),
            'parents' => $this->parentOptions($access, $unit),
            'levels' => $this->hierarchy->formConfig(),
            'canCreateRoot' => $access->visibleUnitIds(auth('admin')->user()) === null || $unit->parent_id === null,
        ]);
    }

    public function update(Request $request, OrganizationalUnit $unit, OrganizationAccess $access): RedirectResponse
    {
        $access->assertCanAccess(auth('admin')->user(), $unit->id);
        $data = $this->validated($request, $unit);
        // Field induk tidak terkirim untuk jenis tingkat teratas; anggap kosong agar induk lama ikut dilepas.
        $data['parent_id'] = $data['parent_id'] ?? null;

        $descendantIds = $this->descendantIds($unit);
        if ($data['parent_id'] && ((int) $data['parent_id'] === $unit->id || in_array((int) $data['parent_id'], $descendantIds, true))) {
            throw ValidationException::withMessages(['parent_id' => 'Unit tidak dapat dipindahkan ke dalam dirinya sendiri atau turunannya.']);
        }

        $type = OrganizationalUnitType::query()->findOrFail($data['type_id']);
        $this->hierarchy->validateParent($type, $data['parent_id'], $unit);
        if ((int) $data['type_id'] !== (int) $unit->type_id) {
            $this->hierarchy->validateChildrenCompatible($unit, $type);
        }
        $this->assertParentAccess($data['parent_id'] !== null ? (int) $data['parent_id'] : null, $access, $unit);

        if ($unit->status === 'active' && $data['status'] === 'inactive') {
            $hasActiveChildren = $unit->children()->where('status', 'active')->exists();
            $hasActivePositions = $unit->positions()->where('is_active', true)->exists();
            $hasCurrentAssignments = $unit->assignments()
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
                ->exists();

            if ($hasActiveChildren || $hasActivePositions || $hasCurrentAssignments) {
                throw ValidationException::withMessages([
                    'status' => 'Unit belum dapat dinonaktifkan. Nonaktifkan unit turunan dan jabatan aktif, serta akhiri penugasan yang masih berlaku terlebih dahulu.',
                ]);
            }
        }

        $before = $unit->only(['type_id', 'parent_id', 'code', 'name', 'description', 'address', 'status']);
        DB::transaction(function () use ($unit, $data, $before) {
            $unit->update($data);
            $this->recordHistory($unit, 'updated', 'Data unit organisasi diperbarui.', [
                'before' => $before,
                'after' => $unit->only(['type_id', 'parent_id', 'code', 'name', 'description', 'address', 'status']),
            ]);
            AdminAuditLog::record('organization.unit_updated', auth('admin')->user(), $unit, [
                'from_parent_id' => $before['parent_id'], 'to_parent_id' => $unit->parent_id,
                'from_status' => $before['status'], 'to_status' => $unit->status,
            ]);
        });

        return redirect()->route('admin.organization.units.show', $unit)->with('success', 'Unit organisasi berhasil diperbarui.');
    }

    private function validated(Request $request, ?OrganizationalUnit $unit = null): array
    {
        $code = [
            'required', 'string', 'min:1', 'max:40',
            Rule::unique('organizational_units', 'code')->ignore($unit?->id),
        ];
        // Format kode diperketat untuk kode baru/berubah; kode lama yang tidak diubah tetap lolos.
        if (! $unit || (string) $unit->code !== (string) $request->input('code')) {
            $code[] = 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/';
        }

        return $request->validate([
            'type_id' => ['required', 'integer', Rule::exists('organizational_unit_types', 'id')->where('is_active', true)],
            'parent_id' => ['nullable', 'integer', Rule::exists('organizational_units', 'id')->where('status', 'active')],
            'code' => $code,
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'type_id.required' => 'Pilih jenis unit terlebih dahulu.',
            'type_id.exists' => 'Jenis unit tidak valid atau sudah tidak aktif.',
            'parent_id.exists' => 'Unit induk tidak ditemukan atau sudah nonaktif.',
            'code.required' => 'Kode unit wajib diisi.',
            'code.max' => 'Kode unit maksimal :max karakter.',
            'code.unique' => 'Kode ":input" sudah dipakai unit lain. Gunakan kode yang berbeda.',
            'code.regex' => 'Kode hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung (diawali huruf/angka), tanpa spasi.',
            'name.required' => 'Nama unit wajib diisi.',
            'name.min' => 'Nama unit minimal :min karakter.',
            'name.max' => 'Nama unit maksimal :max karakter.',
            'address.max' => 'Alamat maksimal :max karakter.',
            'description.max' => 'Keterangan maksimal :max karakter.',
            'status.required' => 'Pilih status unit.',
            'status.in' => 'Status unit tidak valid.',
        ]);
    }

    /** Cek ketersediaan kode secara langsung dari form (wizard). Hanya mengembalikan tersedia/tidak, tanpa membuka data unit lain. */
    public function checkCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'ignore' => ['nullable', 'integer'],
        ]);

        $taken = OrganizationalUnit::query()
            ->where('code', $data['code'])
            ->when($data['ignore'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        return response()->json(['available' => ! $taken]);
    }

    private function assertParentAccess(?int $parentId, OrganizationAccess $access, ?OrganizationalUnit $current = null): void
    {
        $admin = auth('admin')->user();
        $visibleIds = $access->visibleUnitIds($admin);

        if ($parentId === null && $visibleIds !== null && (! $current || $current->parent_id !== null)) {
            abort(403, 'Hanya pengelola dengan cakupan penuh yang dapat membuat unit induk.');
        }
        if ($parentId !== null) {
            $access->assertCanAccess($admin, $parentId);
        }
    }

    /**
     * Jenis yang boleh dipilih di form. Jenis tunggal (mis. Pengurus Besar) disembunyikan
     * bila sudah dipakai unit lain.
     *
     * @return Collection<int, OrganizationalUnitType>
     */
    private function selectableTypes(?OrganizationalUnit $current = null): Collection
    {
        return OrganizationalUnitType::query()->where('is_active', true)->orderBy('name')->get()
            ->reject(function (OrganizationalUnitType $type) use ($current) {
                if (! $this->hierarchy->isSingleton($type->slug)) {
                    return false;
                }

                return OrganizationalUnit::query()
                    ->where('type_id', $type->id)
                    ->when($current, fn ($query) => $query->whereKeyNot($current->id))
                    ->exists();
            })
            ->values();
    }

    /**
     * Calon induk: hanya unit aktif, dapat diakses, dan hierarkinya valid. Jalur lengkap
     * ditampilkan agar pilihan tidak membingungkan.
     *
     * @return Collection<int, OrganizationalUnit>
     */
    private function parentOptions(OrganizationAccess $access, ?OrganizationalUnit $current = null): Collection
    {
        $visibleIds = $access->visibleUnitIds(auth('admin')->user());
        $all = OrganizationalUnit::query()
            ->with('type:id,slug,name')
            ->get(['id', 'type_id', 'parent_id', 'code', 'name', 'status'])
            ->keyBy('id');

        $broken = $this->hierarchy->brokenUnitIds();
        $excluded = $current ? [$current->id, ...$this->descendantIds($current)] : [];

        return $all
            ->filter(fn (OrganizationalUnit $candidate) => $candidate->status === 'active'
                && ($visibleIds === null || in_array($candidate->id, $visibleIds, true))
                && ! in_array($candidate->id, $excluded, true)
                && ! in_array($candidate->id, $broken, true))
            ->map(function (OrganizationalUnit $candidate) use ($all) {
                $names = [];
                $cursor = $candidate;
                $guard = 0;
                while ($cursor && $guard++ < 25) {
                    array_unshift($names, $cursor->name);
                    $cursor = $cursor->parent_id ? $all->get($cursor->parent_id) : null;
                }
                $candidate->setAttribute('path', implode(' › ', $names));

                return $candidate;
            })
            ->sortBy('path', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function descendantIds(OrganizationalUnit $unit): array
    {
        $seen = [];
        $frontier = [$unit->id];
        while ($frontier !== []) {
            $children = OrganizationalUnit::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $frontier = array_values(array_diff($children, $seen, [$unit->id]));
            $seen = array_values(array_unique([...$seen, ...$frontier]));
        }

        return $seen;
    }

    private function applyScope(Builder $query, ?array $visibleIds): void
    {
        if ($visibleIds !== null) {
            $query->whereIn('id', $visibleIds);
        }
    }

    private function recordHistory(OrganizationalUnit $unit, string $action, string $summary, array $details = []): void
    {
        OrganizationalUnitHistory::create([
            'organizational_unit_id' => $unit->id,
            'actor_admin_id' => auth('admin')->id(),
            'action' => $action,
            'summary' => $summary,
            'details' => $details ?: null,
            'created_at' => now(),
        ]);
    }
}
