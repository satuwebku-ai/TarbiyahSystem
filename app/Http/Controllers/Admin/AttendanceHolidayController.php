<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\AttendanceHoliday;
use App\Services\EmployeeAccess;
use App\Services\OrganizationAccess;
use App\Services\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceHolidayController extends Controller
{
    public function index(Request $request, EmployeeAccess $access, OrganizationAccess $org, SchoolScope $schools): View|string
    {
        $admin = $request->user('admin');
        $visible = $org->visibleUnitIds($admin);
        $year = $request->integer('year') ?: (int) now()->year;

        // KOMWIL → KOMDA → Sekolah: tampilkan libur umum + libur yang berlaku pada unit terpilih
        // (unit itu sendiri, induknya yang berlaku ke bawah, dan turunannya).
        $filters = $schools->filters($request);
        $related = $schools->relatedUnitIds($admin, $filters);
        $units = $access->availableUnits($admin);

        return view('admin.attendance.holidays', [
            'holidays' => AttendanceHoliday::query()->with('unit:id,name')
                ->whereYear('holiday_date', $year)
                ->when($visible !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('organizational_unit_id')->orWhereIn('organizational_unit_id', $visible)))
                ->when($related !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('organizational_unit_id')->orWhereIn('organizational_unit_id', $related)))
                ->orderBy('holiday_date')->get(),
            'year' => $year,
            'units' => $units,
            'unitOptions' => $access->unitOptions($units),
            'canGlobal' => $visible === null,
            'scope' => $schools->options($admin),
            'filters' => $filters,
        ])->fragmentIf($request->ajax(), 'table');
    }

    public function store(Request $request, OrganizationAccess $org): RedirectResponse
    {
        $admin = $request->user('admin');
        $data = $request->validate([
            'holiday_date' => ['required', 'date'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
        ]);
        $this->assertScope($data['organizational_unit_id'] ?? null, $request, $org);

        $unitId = $data['organizational_unit_id'] ?? null;
        $duplicate = AttendanceHoliday::query()->where('holiday_date', $data['holiday_date'])
            ->when($unitId, fn ($q) => $q->where('organizational_unit_id', $unitId), fn ($q) => $q->whereNull('organizational_unit_id'))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['holiday_date' => 'Hari libur pada tanggal dan cakupan ini sudah ada.']);
        }

        $holiday = AttendanceHoliday::create($data);
        AdminAuditLog::record('attendance.holiday_created', $admin, $holiday, ['date' => $data['holiday_date']]);

        return back()->with('success', 'Hari libur ditambahkan.');
    }

    public function destroy(Request $request, AttendanceHoliday $holiday, OrganizationAccess $org): RedirectResponse
    {
        $this->assertScope($holiday->organizational_unit_id, $request, $org);
        $holiday->delete();
        AdminAuditLog::record('attendance.holiday_deleted', $request->user('admin'), $holiday, ['date' => $holiday->holiday_date->toDateString()]);

        return back()->with('success', 'Hari libur dihapus.');
    }

    private function assertScope(?int $unitId, Request $request, OrganizationAccess $org): void
    {
        $admin = $request->user('admin');
        $unitId === null
            ? abort_unless($org->visibleUnitIds($admin) === null, 403)
            : $org->assertCanAccess($admin, $unitId);
    }
}
