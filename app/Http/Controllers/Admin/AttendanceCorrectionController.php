<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CorrectionStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Services\AttendanceService;
use App\Services\EmployeeAccess;
use App\Services\SchoolScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request, EmployeeAccess $access, SchoolScope $schools): View|string
    {
        $admin = $request->user('admin');
        $status = CorrectionStatus::tryFrom((string) $request->query('status')) ?? CorrectionStatus::Pending;

        // KOMWIL → KOMDA → Sekolah: mempersempit pegawai yang punya penempatan di unit terpilih.
        $filters = $schools->filters($request);
        $unitScope = $schools->unitScope($admin, $filters);

        $corrections = AttendanceCorrection::query()
            ->with(['employee:id,name,employee_number', 'reviewer:id,name'])
            ->whereHas('employee', fn ($q) => $access->scopeVisible($q, $admin))
            ->when($unitScope !== null, fn ($q) => $q->whereHas('employee.assignments', fn ($a) => $a->whereIn('organizational_unit_id', $unitScope)))
            ->where('status', $status)
            ->orderBy('attendance_date')
            ->paginate(20)->withQueryString();

        return view('admin.attendance.corrections', [
            'corrections' => $corrections,
            'status' => $status,
            'scope' => $schools->options($admin),
            'filters' => $filters,
        ])->fragmentIf($request->ajax(), 'table');
    }

    public function approve(Request $request, AttendanceCorrection $correction, EmployeeAccess $access, AttendanceService $service): RedirectResponse
    {
        return $this->review($request, $correction, true, $access, $service);
    }

    public function reject(Request $request, AttendanceCorrection $correction, EmployeeAccess $access, AttendanceService $service): RedirectResponse
    {
        return $this->review($request, $correction, false, $access, $service);
    }

    private function review(Request $request, AttendanceCorrection $correction, bool $approve, EmployeeAccess $access, AttendanceService $service): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $correction->employee);
        $data = $request->validate(['review_note' => [$approve ? 'nullable' : 'required', 'string', 'max:500']]);

        $service->reviewCorrection($correction, $approve, $data['review_note'] ?? null, $admin);

        return back()->with('success', $approve ? 'Koreksi disetujui.' : 'Koreksi ditolak.');
    }
}
