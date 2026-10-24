<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CorrectionStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Services\AttendanceService;
use App\Services\EmployeeAccess;
use App\Services\SchoolScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request, EmployeeAccess $access, SchoolScope $schools): View|JsonResponse
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
            ->where('status', $status);

        if (Dt::wants($request)) {
            return DataTables::eloquent($corrections->reorder())
                ->addColumn('employee_name', fn (AttendanceCorrection $c) => Dt::stack($c->employee->name, $c->employee->employee_number))
                ->editColumn('attendance_date', fn (AttendanceCorrection $c) => '<span class="text-nowrap">'.e($c->attendance_date->format('d M Y')).'</span>')
                ->addColumn('requested', fn (AttendanceCorrection $c) => '<span class="text-nowrap">'.e($c->requested_check_in ? substr($c->requested_check_in, 0, 5) : '—').' – '.e($c->requested_check_out ? substr($c->requested_check_out, 0, 5) : '—').'</span>')
                ->editColumn('reason', fn (AttendanceCorrection $c) => e($c->reason).($c->review_note ? '<div class="small text-secondary">Catatan: '.e($c->review_note).' ('.e($c->reviewer?->name).')</div>' : ''))
                ->addColumn('action', fn (AttendanceCorrection $c) => view('admin.attendance._correction-actions', ['c' => $c])->render())
                ->orderColumn('employee_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'employees', 'employee_id', 'attendance_corrections'))
                ->filterColumn('employee_name', fn ($q, $kw) => Dt::filterRelated($q, 'employee_id', 'employees', ['name', 'employee_number'], $kw))
                ->rawColumns(['employee_name', 'attendance_date', 'requested', 'reason', 'action'])
                ->make(true);
        }

        return view('admin.attendance.corrections', [
            'status' => $status,
            'scope' => $schools->options($admin),
            'filters' => $filters,
        ]);
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
