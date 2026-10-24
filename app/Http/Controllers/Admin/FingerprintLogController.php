<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Services\FingerprintAttendanceService;
use App\Services\StudentAttendanceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Support\Dt;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FingerprintLogController extends Controller
{
    public function index(Request $request, StudentAttendanceAccess $access): View|JsonResponse
    {
        $admin = $request->user('admin');
        if (Dt::wants($request)) {
            return $this->datatable($request, $access);
        }
        $schools = $access->schools($admin)->with('unit:id,name')->get()->sortBy('unit.name')->values();
        $devices = FingerprintDevice::query()->whereIn('school_id', $schools->pluck('id'))->orderBy('name')->get();

        $unmapped = $this->filtered($request, $access)
            ->where('result', 'unmapped')
            ->selectRaw('pin, COUNT(*) as total, MAX(punched_at) as last_at')
            ->groupBy('pin')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        return view('admin.academic.fingerprint.logs.index', [
            'schools' => $schools,
            'devices' => $devices,
            'results' => FingerprintLog::RESULTS,
            'unmapped' => $unmapped,
            'filters' => $request->only(['school_id', 'device_id', 'result', 'date_from', 'date_to', 'pin']),
            'canManage' => $admin->can('education.fingerprint.manage'),
        ]);
    }

    private function datatable(Request $request, StudentAttendanceAccess $access): JsonResponse
    {
        $query = $this->filtered($request, $access)->with(['device:id,name,serial_number', 'student:id,name,nis', 'school.unit:id,name']);

        return DataTables::eloquent($query->reorder())
            ->editColumn('punched_at', fn (FingerprintLog $l) => '<span class="text-nowrap">'.e(\App\Helpers\AttendanceClock::toLocal($l->punched_at)->format('d M Y H:i:s')).'</span>')
            ->addColumn('student_name', fn (FingerprintLog $l) => $l->student
                ? Dt::stack($l->student->name, 'NIS '.$l->student->nis)
                : '<span class="text-secondary">—</span>')
            ->addColumn('device_name', fn (FingerprintLog $l) => e($l->device?->name).'<div class="small text-secondary">'.e($l->school?->unit?->name).'</div>')
            ->editColumn('result', fn (FingerprintLog $l) => '<span class="badge '.e($l->resultBadge()).'">'.e($l->resultLabel()).'</span>'
                .($l->note ? '<div class="small text-secondary">'.e($l->note).'</div>' : ''))
            ->orderColumn('student_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'students', 'student_id', 'fingerprint_logs'))
            ->orderColumn('device_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'fingerprint_devices', 'fingerprint_device_id', 'fingerprint_logs'))
            ->filterColumn('student_name', fn ($q, $kw) => Dt::filterRelated($q, 'student_id', 'students', ['name', 'nis'], $kw))
            ->filterColumn('device_name', fn ($q, $kw) => Dt::filterRelated($q, 'fingerprint_device_id', 'fingerprint_devices', ['name'], $kw))
            ->rawColumns(['punched_at', 'student_name', 'device_name', 'result'])
            ->make(true);
    }

    public function reprocess(Request $request, StudentAttendanceAccess $access, FingerprintAttendanceService $service): RedirectResponse
    {
        $changed = $service->reprocess($this->filtered($request, $access));
        AdminAuditLog::record('fingerprint.logs_reprocessed', $request->user('admin'), null, ['changed' => $changed]);

        return back()->with('success', $changed > 0
            ? "{$changed} log berhasil diproses ulang."
            : 'Tidak ada log yang berubah. Pastikan PIN siswa sudah dipetakan dan siswa punya penempatan kelas.');
    }

    private function filtered(Request $request, StudentAttendanceAccess $access): Builder
    {
        $request->validate([
            'school_id' => ['nullable', 'integer'],
            'device_id' => ['nullable', 'integer'],
            'result' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'pin' => ['nullable', 'string', 'max:30'],
        ]);

        $schoolIds = $access->schools($request->user('admin'))->pluck('id');

        return FingerprintLog::query()
            ->whereIn('school_id', $schoolIds)
            ->when($request->integer('school_id'), fn (Builder $q, int $id) => $q->where('school_id', $id))
            ->when($request->integer('device_id'), fn (Builder $q, int $id) => $q->where('fingerprint_device_id', $id))
            ->when($request->filled('result'), fn (Builder $q) => $q->where('result', $request->string('result')))
            ->when($request->filled('pin'), fn (Builder $q) => $q->where('pin', trim((string) $request->input('pin'))))
            ->when($request->filled('date_from'), fn (Builder $q) => $q->where('punched_at', '>=', \App\Helpers\AttendanceClock::fromLocal($request->input('date_from'), '00:00:00')))
            ->when($request->filled('date_to'), fn (Builder $q) => $q->where('punched_at', '<=', \App\Helpers\AttendanceClock::fromLocal($request->input('date_to'), '23:59:59')));
    }
}
