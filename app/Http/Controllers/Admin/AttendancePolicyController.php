<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AttendancePolicy;
use App\Services\EmployeeAccess;
use App\Services\OrganizationAccess;
use App\Services\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendancePolicyController extends Controller
{
    public function index(Request $request, OrganizationAccess $org, SchoolScope $schools): View|JsonResponse
    {
        $admin = $request->user('admin');
        $visible = $org->visibleUnitIds($admin);

        // KOMWIL → KOMDA → Sekolah: kebijakan umum + kebijakan pada unit terpilih (unit itu, induk, dan turunannya).
        $filters = $schools->filters($request);
        $related = $schools->relatedUnitIds($admin, $filters);

        if (Dt::wants($request)) {
            $query = AttendancePolicy::query()->with('unit:id,name,code')
                // Seperti Struktur Organisasi: pengelola terbatas hanya melihat unit dalam cakupannya (tanpa kebijakan umum/induk).
                ->when($visible !== null, fn ($q) => $q->whereIn('organizational_unit_id', $visible))
                ->when($related !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('organizational_unit_id')->orWhereIn('organizational_unit_id', $related)));

            return DataTables::eloquent($query->reorder())
                ->editColumn('name', fn (AttendancePolicy $p) => '<span class="fw-semibold">'.e($p->name).'</span>')
                ->addColumn('unit_name', fn (AttendancePolicy $p) => e($p->unit?->name ?? 'Umum (seluruh organisasi)'))
                ->addColumn('days', fn (AttendancePolicy $p) => e($p->dayLabels()))
                ->addColumn('hours', fn (AttendancePolicy $p) => '<span class="text-nowrap">'.e(substr($p->work_start, 0, 5)).'–'.e(substr($p->work_end, 0, 5)).'</span>')
                ->addColumn('tz', fn (AttendancePolicy $p) => '<span class="text-nowrap">'.e(\App\Helpers\AttendanceClock::abbr($p->zone())).'</span>')
                ->editColumn('late_tolerance_minutes', fn (AttendancePolicy $p) => e($p->late_tolerance_minutes).' menit')
                ->editColumn('is_active', fn (AttendancePolicy $p) => Dt::badge((bool) $p->is_active))
                ->addColumn('action', fn (AttendancePolicy $p) => view('admin.attendance.policies._actions', ['p' => $p, 'isUnrestricted' => $visible === null])->render())
                ->orderColumn('unit_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'organizational_units', 'organizational_unit_id', 'attendance_policies'))
                ->filterColumn('unit_name', fn ($q, $kw) => Dt::filterRelated($q, 'organizational_unit_id', 'organizational_units', ['name', 'code'], $kw))
                ->rawColumns(['name', 'unit_name', 'days', 'hours', 'tz', 'is_active', 'action'])
                ->make(true);
        }

        return view('admin.attendance.policies.index', [
            'scope' => $schools->options($admin),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request, EmployeeAccess $access, OrganizationAccess $org): View
    {
        return $this->form(new AttendancePolicy(['work_days' => [1, 2, 3, 4, 5], 'work_start' => '07:00', 'work_end' => '15:00', 'late_tolerance_minutes' => 15, 'timezone' => config('attendance.timezone'), 'is_active' => true]), $request, $access, $org);
    }

    public function store(Request $request, OrganizationAccess $org): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertScope($data['organizational_unit_id'] ?? null, $request, $org);
        $this->assertSingleActive($data);

        $policy = AttendancePolicy::create($data);
        AdminAuditLog::record('attendance.policy_created', $request->user('admin'), $policy, ['unit_id' => $policy->organizational_unit_id]);

        return redirect()->route('admin.attendance.policies.index')->with('success', 'Kebijakan absensi ditambahkan.');
    }

    public function edit(Request $request, AttendancePolicy $policy, EmployeeAccess $access, OrganizationAccess $org): View
    {
        $this->assertScope($policy->organizational_unit_id, $request, $org);

        return $this->form($policy, $request, $access, $org);
    }

    public function update(Request $request, AttendancePolicy $policy, OrganizationAccess $org): RedirectResponse
    {
        $this->assertScope($policy->organizational_unit_id, $request, $org);
        $data = $this->validated($request);
        $this->assertScope($data['organizational_unit_id'] ?? null, $request, $org);
        $this->assertSingleActive($data, $policy);

        $policy->update($data);
        AdminAuditLog::record('attendance.policy_updated', $request->user('admin'), $policy, ['changed_fields' => array_keys($policy->getChanges())]);

        return redirect()->route('admin.attendance.policies.index')->with('success', 'Kebijakan absensi diperbarui.');
    }

    public function toggle(Request $request, AttendancePolicy $policy, OrganizationAccess $org): RedirectResponse
    {
        $this->assertScope($policy->organizational_unit_id, $request, $org);
        $this->assertSingleActive(['is_active' => ! $policy->is_active, 'organizational_unit_id' => $policy->organizational_unit_id], $policy);

        $policy->update(['is_active' => ! $policy->is_active]);
        AdminAuditLog::record('attendance.policy_updated', $request->user('admin'), $policy, ['changed_fields' => ['is_active']]);

        return back()->with('success', $policy->is_active ? 'Kebijakan diaktifkan.' : 'Kebijakan dinonaktifkan.');
    }

    private function form(AttendancePolicy $policy, Request $request, EmployeeAccess $access, OrganizationAccess $org): View
    {
        return view('admin.attendance.policies.form', [
            'policy' => $policy,
            'units' => $units = $access->availableUnits($request->user('admin')),
            'unitOptions' => $access->unitOptions($units),
            'canGlobal' => $org->visibleUnitIds($request->user('admin')) === null,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7'],
            'work_start' => ['required', 'date_format:H:i'],
            'work_end' => ['required', 'date_format:H:i', 'after:work_start'],
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,240'],
            'checkin_deadline' => ['nullable', 'date_format:H:i', 'after:work_start', 'before:work_end'],
            'timezone' => ['required', 'string', Rule::in(array_keys(config('attendance.timezones')))],
            'is_active' => ['required', 'boolean'],
            'require_location' => ['sometimes', 'boolean'],
            'require_photo' => ['sometimes', 'boolean'],
            'geo_latitude' => ['nullable', 'required_if:require_location,1', 'numeric', 'between:-90,90'],
            'geo_longitude' => ['nullable', 'required_if:require_location,1', 'numeric', 'between:-180,180'],
            'geo_radius_m' => ['nullable', 'required_if:require_location,1', 'integer', 'between:20,5000'],
        ], [
            'checkin_deadline.after' => 'Batas check-in harus setelah jam masuk.',
            'checkin_deadline.before' => 'Batas check-in harus sebelum jam pulang.',
            'geo_latitude.required_if' => 'Isi titik lokasi sekolah (latitude) bila absen wajib di lokasi.',
            'geo_longitude.required_if' => 'Isi titik lokasi sekolah (longitude) bila absen wajib di lokasi.',
            'geo_radius_m.required_if' => 'Isi radius yang diizinkan bila absen wajib di lokasi.',
        ]);
        if (array_key_exists('geo_radius_m', $data) && $data['geo_radius_m'] === null) {
            $data['geo_radius_m'] = 100;
        }
        $data['work_days'] = collect($data['work_days'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();

        return $data;
    }

    /** Kebijakan umum hanya untuk pengelola tanpa batas unit; kebijakan unit harus dalam cakupan. */
    private function assertScope(?int $unitId, Request $request, OrganizationAccess $org): void
    {
        $admin = $request->user('admin');
        $unitId === null
            ? abort_unless($org->visibleUnitIds($admin) === null, 403)
            : $org->assertCanAccess($admin, $unitId);
    }

    private function assertSingleActive(array $data, ?AttendancePolicy $ignore = null): void
    {
        if (empty($data['is_active'])) {
            return;
        }
        $unitId = $data['organizational_unit_id'] ?? null;
        $exists = AttendancePolicy::query()->where('is_active', true)
            ->when($unitId, fn ($q) => $q->where('organizational_unit_id', $unitId), fn ($q) => $q->whereNull('organizational_unit_id'))
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['organizational_unit_id' => 'Unit ini sudah punya kebijakan aktif. Nonaktifkan yang lama terlebih dahulu.']);
        }
    }
}
