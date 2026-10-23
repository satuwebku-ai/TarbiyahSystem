<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\EmployeeAccess;
use App\Services\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Pencatatan izin, sakit, cuti, dan tidak hadir oleh pengelola. */
class AttendanceEntryController extends Controller
{
    public function index(Request $request, EmployeeAccess $access, SchoolScope $schools): View|string
    {
        $admin = $request->user('admin');

        // KOMWIL → KOMDA → Sekolah menyaring riwayat; dropdown pegawai di formulir ikut tersaring di sisi klien.
        $filters = $schools->filters($request);
        $unitScope = $schools->unitScope($admin, $filters);

        $employees = $access->scopeVisible(Employee::query(), $admin)
            ->whereNotIn('status', [EmployeeStatus::Resigned, EmployeeStatus::Retired])
            ->orderBy('name')->get(['id', 'name', 'employee_number']);

        return view('admin.attendance.entries', [
            'employees' => $employees,
            'employeeAttrs' => $schools->employeeAttributes($employees),
            'statuses' => array_filter(AttendanceStatus::cases(), fn ($s) => $s->isManual()),
            'entries' => AttendanceRecord::query()
                ->with(['employee:id,name,employee_number', 'unit:id,name'])
                ->where('source', 'manual')
                ->whereHas('employee', fn ($q) => $access->scopeVisible($q, $admin))
                ->when($unitScope !== null, fn ($q) => $q->whereIn('organizational_unit_id', $unitScope))
                ->orderByDesc('attendance_date')->orderByDesc('id')
                ->paginate(20)->withQueryString(),
            'scope' => $schools->options($admin),
            'filters' => $filters,
        ])->fragmentIf($request->ajax(), 'table');
    }

    public function store(Request $request, EmployeeAccess $access, AttendanceService $service): RedirectResponse
    {
        $admin = $request->user('admin');
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, array_filter(AttendanceStatus::cases(), fn ($s) => $s->isManual())))],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $access->assertCanManage($admin, $employee);

        $days = $service->recordEntries($employee, AttendanceStatus::from($data['status']), $data['date_from'], $data['date_to'], $data['notes'] ?? null, $admin);

        return back()->with('success', "{$days} hari kerja dicatat.");
    }

    public function destroy(Request $request, AttendanceRecord $record, EmployeeAccess $access, AttendanceService $service): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $record->employee);
        $service->deleteEntry($record, $admin);

        return back()->with('success', 'Catatan dihapus.');
    }
}
