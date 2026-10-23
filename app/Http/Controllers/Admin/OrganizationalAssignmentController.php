<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitHistory;
use App\Models\UserUnitAccess;
use App\Services\OrganizationAccess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizationalAssignmentController extends Controller
{
    public function index(Request $request, OrganizationAccess $access): View
    {
        $visibleIds = $access->visibleUnitIds(auth('admin')->user());
        $visibleUnits = $this->visibleUnits($visibleIds);
        $visibleById = $visibleUnits->keyBy('id');
        $view = $request->query('view') === 'table' ? 'table' : 'tree';
        $status = in_array($request->query('status'), ['active', 'ended'], true) ? $request->query('status') : null;
        $search = mb_strtolower(trim((string) $request->query('search')));

        // Unit terpilih berlaku untuk unit itu beserta turunannya, sama seperti bagan jabatan.
        $selectedUnitId = $request->integer('unit_id') ?: null;
        $units = $visibleUnits;
        if ($selectedUnitId && $visibleById->has($selectedUnitId)) {
            $subtree = $access->withDescendants([$selectedUnitId]);
            $units = $visibleUnits->filter(fn (OrganizationalUnit $unit) => in_array($unit->id, $subtree, true))->values();
        } else {
            $selectedUnitId = null;
        }
        $unitIds = $units->pluck('id')->all();

        $common = [
            'view' => $view,
            'unitOptions' => $this->unitOptions($visibleUnits, $visibleById),
            'selectedUnitId' => $selectedUnitId,
            'filters' => ['status' => $status, 'search' => $request->query('search')],
        ];

        if ($view === 'table') {
            $query = OrganizationalAssignment::query()
                ->with(['unit:id,name,code', 'position:id,name', 'admin:id,name,username', 'employee:id,name,employee_number'])
                ->whereIn('organizational_unit_id', $unitIds)
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                    ->whereRaw('LOWER(assignee_name) LIKE ?', ['%'.$search.'%'])
                    ->orWhereHas('position', fn ($p) => $p->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']))))
                ->orderByDesc('start_date');

            return view('admin.organization.assignments.index', $common + [
                'assignments' => $query->paginate(25)->withQueryString(),
            ]);
        }

        $assignments = OrganizationalAssignment::query()
            ->with(['position:id,name', 'admin:id,name,username', 'employee:id,name,employee_number'])
            ->whereIn('organizational_unit_id', $unitIds)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->get()
            ->sortBy(fn (OrganizationalAssignment $a) => mb_strtolower((string) $a->position?->name).'|'.(9999999999 - $a->start_date->timestamp))
            ->groupBy('organizational_unit_id');

        $display = [];
        $included = [];
        $byId = $units->keyBy('id');
        foreach ($units as $unit) {
            $unitMatch = $search === ''
                || str_contains(mb_strtolower($unit->name), $search)
                || str_contains(mb_strtolower($unit->code), $search);

            $rows = [];
            foreach ($assignments->get($unit->id, collect()) as $assignment) {
                $hit = $search !== '' && (
                    str_contains(mb_strtolower((string) $assignment->assignee_name), $search)
                    || str_contains(mb_strtolower((string) $assignment->position?->name), $search)
                );
                if ($unitMatch || $hit) {
                    $rows[] = ['assignment' => $assignment, 'match' => $hit];
                }
            }
            $display[$unit->id] = ['rows' => $rows, 'match' => $search !== '' && $unitMatch];

            // Tanpa filter semua unit tampil; dengan filter hanya unit berisi hasil beserta induknya.
            $show = ($search === '' && $status === null) || $rows !== [] || ($search !== '' && $unitMatch);
            if ($show) {
                $cursor = $unit;
                for ($guard = 0; $cursor && $guard < 25; $guard++) {
                    $included[$cursor->id] = true;
                    $cursor = $cursor->parent_id ? $byId->get($cursor->parent_id) : null;
                }
            }
        }
        $shown = $units->filter(fn (OrganizationalUnit $unit) => isset($included[$unit->id]))->values();

        $scopeRoots = $visibleIds === null
            ? []
            : $visibleUnits->filter(fn (OrganizationalUnit $unit) => ! $unit->parent_id || ! $visibleById->has($unit->parent_id))
                ->pluck('name')->values()->all();

        return view('admin.organization.assignments.index', $common + [
            'tree' => $this->makeTree($shown, $display),
            'filtered' => $search !== '' || $status !== null,
            'scopeRoots' => $scopeRoots,
            'assignmentCount' => $assignments->flatten(1)->count(),
        ]);
    }

    public function create(Request $request, OrganizationAccess $access): View
    {
        $positionId = $request->integer('position_id') ?: null;
        $unitId = $request->integer('unit_id') ?: null;
        if ($positionId && ! $unitId) {
            $unitId = OrganizationalPosition::query()->whereKey($positionId)->value('organizational_unit_id');
        }

        return view('admin.organization.assignments.form', [
            'assignment' => new OrganizationalAssignment([
                'start_date' => today()->toDateString(),
                'organizational_unit_id' => $unitId,
                'organizational_position_id' => $positionId,
            ]),
            ...$this->formData($access),
        ]);
    }

    public function store(Request $request, OrganizationAccess $access): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertAssignmentScope($data, $access);
        $this->assertNoOverlap($data);

        $assignment = DB::transaction(function () use ($data) {
            $assignment = OrganizationalAssignment::create([
                ...$data,
                'status' => $this->statusFor($data['end_date']),
            ]);
            $this->recordHistory(
                $assignment->organizational_unit_id,
                'assignment_created',
                "{$assignment->assignee_name} ditugaskan sebagai {$assignment->position->name}."
            );

            AdminAuditLog::record('organization.assignment_created', auth('admin')->user(), $assignment, [
                'unit_id' => $assignment->organizational_unit_id, 'position_id' => $assignment->organizational_position_id,
            ]);

            return $assignment;
        });

        return redirect()->route('admin.organization.assignments.index')->with('success', 'Penugasan berhasil ditambahkan.');
    }

    public function edit(OrganizationalAssignment $assignment, OrganizationAccess $access): View
    {
        $access->assertCanAccess(auth('admin')->user(), $assignment->organizational_unit_id);

        return view('admin.organization.assignments.form', [
            'assignment' => $assignment,
            // Unit, jabatan, akun, dan pegawai saat ini tetap tampil walau sudah tidak memenuhi syarat,
            // agar penugasan lama tetap bisa diubah.
            ...$this->formData($access, $assignment),
        ]);
    }

    public function update(Request $request, OrganizationalAssignment $assignment, OrganizationAccess $access): RedirectResponse
    {
        $access->assertCanAccess(auth('admin')->user(), $assignment->organizational_unit_id);
        $data = $this->validated($request);
        $this->assertAssignmentScope($data, $access, $assignment);
        $this->assertNoOverlap($data, $assignment);

        $oldUnitId = $assignment->organizational_unit_id;
        $assignment->update([
            ...$data,
            'status' => $this->statusFor($data['end_date']),
        ]);
        AdminAuditLog::record('organization.assignment_updated', auth('admin')->user(), $assignment, [
            'from_unit_id' => $oldUnitId, 'to_unit_id' => $assignment->organizational_unit_id, 'position_id' => $assignment->organizational_position_id,
        ]);
        $this->recordHistory($oldUnitId, 'assignment_updated', "Penugasan {$assignment->assignee_name} diperbarui.");
        if ($oldUnitId !== $assignment->organizational_unit_id) {
            $this->recordHistory($assignment->organizational_unit_id, 'assignment_moved', 'Penugasan dipindahkan ke unit ini.');
        }

        return redirect()->route('admin.organization.assignments.index')->with('success', 'Penugasan berhasil diperbarui.');
    }

    public function end(Request $request, OrganizationalAssignment $assignment, OrganizationAccess $access): RedirectResponse
    {
        $access->assertCanAccess(auth('admin')->user(), $assignment->organizational_unit_id);
        $data = $request->validate([
            'end_date' => ['required', 'date', 'after_or_equal:'.$assignment->start_date->toDateString()],
        ]);

        $assignment->update(['end_date' => $data['end_date'], 'status' => 'ended']);
        AdminAuditLog::record('organization.assignment_ended', auth('admin')->user(), $assignment, ['end_date' => $data['end_date']]);
        $this->recordHistory(
            $assignment->organizational_unit_id,
            'assignment_ended',
            "Penugasan {$assignment->assignee_name} sebagai {$assignment->position->name} diakhiri."
        );

        return back()->with('success', 'Penugasan telah diakhiri.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'organizational_unit_id' => ['required', 'integer', Rule::exists('organizational_units', 'id')],
            'organizational_position_id' => ['required', 'integer', Rule::exists('organizational_positions', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'admin_id' => ['nullable', 'integer', Rule::exists('admins', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [
            'employee_id.required' => 'Pilih pegawai yang ditugaskan.',
        ]);

        // Permintaan yang tidak menyertakan kolom opsional tidak boleh membuat galat 500.
        $data['end_date'] ??= null;
        $data['admin_id'] ??= null;

        // Nama selalu berasal dari data pegawai; input nama manual tidak lagi diterima.
        $data['assignee_name'] = Employee::query()->findOrFail($data['employee_id'])->name;

        return $data;
    }

    private function assertAssignmentScope(array $data, OrganizationAccess $access, ?OrganizationalAssignment $current = null): void
    {
        $access->assertCanAccess(auth('admin')->user(), (int) $data['organizational_unit_id']);

        // Jabatan yang tidak diganti boleh sudah nonaktif; penugasan baru wajib memakai jabatan aktif.
        $unchanged = $current
            && (int) $current->organizational_position_id === (int) $data['organizational_position_id']
            && (int) $current->organizational_unit_id === (int) $data['organizational_unit_id'];

        $position = OrganizationalPosition::query()
            ->whereKey($data['organizational_position_id'])
            ->where('organizational_unit_id', $data['organizational_unit_id'])
            ->when(! $unchanged, fn ($query) => $query->where('is_active', true))
            ->first();

        if (! $position) {
            throw ValidationException::withMessages([
                'organizational_position_id' => 'Pilih jabatan aktif yang berada di unit yang sama.',
            ]);
        }

        $this->assertEmployeeInUnit($data, $current);
        $this->assertEmployeeFree($data, $current);
        $this->assertAdminCoversUnit($data, $current);
    }

    /** Pegawai harus aktif dan sedang ditempatkan di unit penugasan (kecuali tidak berubah dari penugasan lama). */
    private function assertEmployeeInUnit(array $data, ?OrganizationalAssignment $current): void
    {
        $unchanged = $current
            && (int) $current->employee_id === (int) $data['employee_id']
            && (int) $current->organizational_unit_id === (int) $data['organizational_unit_id'];
        if ($unchanged) {
            return;
        }

        $placed = $this->availableEmployees($this->scopeIds(), null, (int) $data['organizational_unit_id'], false)
            ->contains('id', (int) $data['employee_id']);

        if (! $placed) {
            throw ValidationException::withMessages([
                'employee_id' => 'Pilih pegawai aktif yang ditempatkan di unit ini (lihat Data Pegawai › Penempatan).',
            ]);
        }
    }

    /** Satu pegawai tidak boleh memegang dua jabatan struktural pada rentang tanggal yang bertumpuk. */
    private function assertEmployeeFree(array $data, ?OrganizationalAssignment $current): void
    {
        $endDate = $data['end_date'] ?: '9999-12-31';
        $other = OrganizationalAssignment::query()
            ->with(['position:id,name', 'unit:id,name'])
            ->where('employee_id', $data['employee_id'])
            ->where('start_date', '<=', $endDate)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $data['start_date']))
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->first();

        if ($other) {
            throw ValidationException::withMessages([
                'employee_id' => "Pegawai ini sudah menjabat {$other->position?->name} di {$other->unit?->name} pada rentang tanggal tersebut. Akhiri penugasan itu terlebih dahulu.",
            ]);
        }
    }

    /** Akun login harus diberi Akses Unit pada unit penugasan, sehingga tidak bisa lintas daerah. */
    private function assertAdminCoversUnit(array $data, ?OrganizationalAssignment $current): void
    {
        if (empty($data['admin_id'])) {
            return;
        }
        $unchanged = $current
            && (int) $current->admin_id === (int) $data['admin_id']
            && (int) $current->organizational_unit_id === (int) $data['organizational_unit_id'];
        if ($unchanged) {
            return;
        }

        $covers = UserUnitAccess::query()
            ->where('model_type', Admin::class)
            ->where('model_id', $data['admin_id'])
            ->where('organizational_unit_id', $data['organizational_unit_id'])
            ->exists();

        if (! $covers) {
            throw ValidationException::withMessages([
                'admin_id' => 'Akun ini tidak memiliki Akses Unit pada unit yang dipilih. Atur dulu di menu Akses Unit.',
            ]);
        }
    }

    /** Penugasan dengan tanggal akhir hari ini atau sebelumnya dianggap selesai (sama seperti tombol Akhiri). */
    private function statusFor(?string $endDate): string
    {
        return $endDate && Carbon::parse($endDate)->lte(today()) ? 'ended' : 'active';
    }

    private function assertNoOverlap(array $data, ?OrganizationalAssignment $ignore = null): void
    {
        $position = OrganizationalPosition::query()->findOrFail($data['organizational_position_id']);
        if ($position->isFull($data['start_date'], $data['end_date'] ?? null, $ignore?->id)) {
            throw ValidationException::withMessages([
                'organizational_position_id' => $position->isSingleHolder()
                    ? 'Rentang tanggal bertumpang tindih dengan penugasan lain pada jabatan ini.'
                    : "Jabatan ini sudah penuh (kapasitas {$position->capacityLabel()}) pada rentang tanggal tersebut.",
            ]);
        }
    }

    /** @return int[]|null null = tanpa batas cakupan */
    private function scopeIds(): ?array
    {
        return app(OrganizationAccess::class)->visibleUnitIds(auth('admin')->user());
    }

    /** Semua unit dalam cakupan admin (semua status), lengkap dengan jenisnya. */
    private function visibleUnits(?array $visibleIds): Collection
    {
        return OrganizationalUnit::query()
            ->with('type:id,name')
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('id', $visibleIds))
            ->orderBy('name')
            ->get();
    }

    /**
     * Pilihan unit berlabel rantai induk ("KOMWIL › KOMDA › Sekolah") agar jelas berada di daerah mana.
     *
     * @param  Collection<int, OrganizationalUnit>  $options
     * @param  Collection<int, OrganizationalUnit>  $labelUnits  keyed by id
     * @return array<int, array{id:int,label:string,code:string}>
     */
    private function unitOptions(Collection $options, Collection $labelUnits): array
    {
        return $options->map(function (OrganizationalUnit $unit) use ($labelUnits) {
            $names = [];
            $cursor = $unit;
            for ($guard = 0; $cursor && $guard < 25; $guard++) {
                array_unshift($names, $cursor->name);
                $cursor = $cursor->parent_id ? $labelUnits->get($cursor->parent_id) : null;
            }

            return ['id' => $unit->id, 'label' => implode(' › ', $names), 'code' => $unit->code];
        })->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
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
                'rows' => $display[$unit->id]['rows'] ?? [],
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

    /** Data untuk form tambah/ubah. Opsi akun dan pegawai membawa daftar unit agar bisa disaring per unit. */
    private function formData(OrganizationAccess $access, ?OrganizationalAssignment $assignment = null): array
    {
        $visibleIds = $access->visibleUnitIds(auth('admin')->user());
        $visibleUnits = $this->visibleUnits($visibleIds);
        $units = $this->availableUnits($visibleUnits, $assignment?->organizational_unit_id);

        return [
            'units' => $units,
            'unitOptions' => $this->unitOptions($units, $visibleUnits->keyBy('id')),
            'positions' => $this->availablePositions($access, $assignment?->organizational_position_id),
            'admins' => $this->availableAdmins($assignment?->admin_id),
            'employees' => $this->availableEmployees($visibleIds, $assignment?->employee_id),
        ];
    }

    private function availableUnits(Collection $visibleUnits, ?int $includeId = null): Collection
    {
        return $visibleUnits
            ->filter(fn (OrganizationalUnit $unit) => $unit->status === 'active' || $unit->id === $includeId)
            ->values();
    }

    private function availablePositions(OrganizationAccess $access, ?int $includeId = null)
    {
        $visibleIds = $access->visibleUnitIds(auth('admin')->user());
        $query = OrganizationalPosition::query()
            ->with('unit:id,name,code')
            ->where(fn ($builder) => $builder->where('is_active', true)->when($includeId, fn ($inner) => $inner->orWhere('id', $includeId)))
            ->orderBy('name');
        if ($visibleIds !== null) {
            $query->whereIn('organizational_unit_id', $visibleIds);
        }

        return $query->get();
    }

    /**
     * Akun panel yang dapat dikaitkan: hanya akun yang diberi Akses Unit. Setiap akun membawa
     * `unit_ids` (unit yang boleh dikaitkan) supaya form hanya menampilkan akun milik unit terpilih.
     */
    private function availableAdmins(?int $includeId = null): Collection
    {
        $access = UserUnitAccess::query()
            ->where('model_type', Admin::class)
            ->get(['model_id', 'organizational_unit_id'])
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->all());

        return Admin::query()
            ->where(fn ($builder) => $builder->where('is_active', true)->when($includeId, fn ($inner) => $inner->orWhere('id', $includeId)))
            ->whereIn('id', $access->keys()->when($includeId, fn ($keys) => $keys->push($includeId))->all())
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email'])
            ->each(fn (Admin $admin) => $admin->setAttribute('unit_ids', $access->get($admin->id, [])));
    }

    /**
     * Pegawai aktif beserta unit tempat ia sedang ditempatkan (`unit_ids`). Bila $onlyUnitId diisi,
     * hanya pegawai yang ditempatkan di unit itu. Pegawai pada penugasan yang sedang diubah ikut dimuat.
     *
     * @param  int[]|null  $visibleIds
     */
    private function availableEmployees(?array $visibleIds, ?int $includeId = null, ?int $onlyUnitId = null, bool $excludeBusy = true): Collection
    {
        $today = today()->toDateString();
        // Pegawai yang sedang memegang jabatan struktural tidak ditawarkan lagi (satu orang, satu jabatan).
        $busy = $excludeBusy
            ? OrganizationalAssignment::query()
                ->whereNotNull('employee_id')
                ->where('status', 'active')
                ->whereDate('start_date', '<=', $today)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
                ->pluck('employee_id')->all()
            : [];
        $placements = EmployeeAssignment::query()
            ->whereDate('start_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->when($visibleIds !== null, fn ($q) => $q->whereIn('organizational_unit_id', $visibleIds))
            ->when($onlyUnitId, fn ($q) => $q->where('organizational_unit_id', $onlyUnitId))
            ->whereHas('employee', fn ($q) => $q->where('status', 'active'))
            ->get(['employee_id', 'organizational_unit_id'])
            ->reject(fn ($row) => in_array($row->employee_id, $busy, true) && $row->employee_id !== $includeId)
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->pluck('organizational_unit_id')->unique()->map(fn ($id) => (int) $id)->values()->all());

        $ids = $placements->keys()->when($includeId, fn ($keys) => $keys->push($includeId))->unique()->all();

        return Employee::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_number', 'employee_type'])
            ->each(fn (Employee $employee) => $employee->setAttribute('unit_ids', $placements->get($employee->id, [])));
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
