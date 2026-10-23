<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Services\FingerprintAttendanceService;
use App\Services\StudentAttendanceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FingerprintLogController extends Controller
{
    public function index(Request $request, StudentAttendanceAccess $access): View
    {
        $admin = $request->user('admin');
        $schools = $access->schools($admin)->with('unit:id,name')->get()->sortBy('unit.name')->values();
        $devices = FingerprintDevice::query()->whereIn('school_id', $schools->pluck('id'))->orderBy('name')->get();

        $logs = $this->filtered($request, $access)
            ->with(['device:id,name,serial_number', 'student:id,name,nis', 'school.unit:id,name'])
            ->latest('punched_at')
            ->paginate(50)
            ->withQueryString();

        $unmapped = $this->filtered($request, $access)
            ->where('result', 'unmapped')
            ->selectRaw('pin, COUNT(*) as total, MAX(punched_at) as last_at')
            ->groupBy('pin')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        return view('admin.academic.fingerprint.logs.index', [
            'logs' => $logs,
            'schools' => $schools,
            'devices' => $devices,
            'results' => FingerprintLog::RESULTS,
            'unmapped' => $unmapped,
            'filters' => $request->only(['school_id', 'device_id', 'result', 'date_from', 'date_to', 'pin']),
            'canManage' => $admin->can('education.fingerprint.manage'),
        ]);
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
