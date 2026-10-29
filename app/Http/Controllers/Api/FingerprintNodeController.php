<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Models\FingerprintDevice;
use App\Models\FingerprintNodeConnection;
use App\Models\StudentAttendanceSetting;
use App\Services\FingerprintAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API untuk aplikasi Node fingerprint di sekolah. Kredensial dicek oleh middleware fingerprint.node.
 *
 *   POST /api/fingerprint-node/connect   uji koneksi, ambil info sekolah + pengaturan
 *   POST /api/fingerprint-node/punches   kirim ketukan jari
 *
 * Body /punches: { "device_sn": "ABC123", "punches": [ {"pin":"101","time":"2026-10-29 06:55:00","status":0,"verify":1} ] }
 * "time" = jam dinding mesin (zona waktu absensi), sama seperti push ADMS.
 */
class FingerprintNodeController extends Controller
{
    public function connect(Request $request): JsonResponse
    {
        $connection = $this->connection($request);
        $this->touch($connection, $request);
        $connection->loadMissing('school.unit');
        $settings = StudentAttendanceSetting::forSchool($connection->school_id);

        return response()->json([
            'ok' => true,
            'school' => ['id' => $connection->school_id, 'name' => $connection->school?->unit?->name],
            'server_time' => AttendanceClock::now()->format('Y-m-d H:i:s'),
            'timezone' => AttendanceClock::now()->getTimezone()->getName(),
            'settings' => [
                'fingerprint_enabled' => $settings->fingerprint_enabled,
                'start_time' => $settings->start_time,
                'late_tolerance_minutes' => $settings->late_tolerance_minutes,
                'checkin_open_time' => $settings->checkin_open_time,
                'checkin_close_time' => $settings->checkin_close_time,
                'school_days' => array_map('intval', $settings->school_days ?? []),
            ],
            'limits' => [
                'max_punches_per_request' => (int) config('student_attendance.max_punches_per_request', 1000),
                'max_body_bytes' => (int) config('student_attendance.max_body_bytes', 262144),
            ],
        ]);
    }

    public function punches(Request $request, FingerprintAttendanceService $service): JsonResponse
    {
        $connection = $this->connection($request);

        if (strlen((string) $request->getContent()) > (int) config('student_attendance.max_body_bytes', 262144)) {
            return response()->json(['ok' => false, 'message' => 'Data terlalu besar.'], 413);
        }

        $data = $request->validate([
            'device_sn' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/'],
            'punches' => ['required', 'array', 'min:1', 'max:'.(int) config('student_attendance.max_punches_per_request', 1000)],
            'punches.*.pin' => ['required', 'regex:/^[A-Za-z0-9._-]{1,30}$/'],
            'punches.*.time' => ['required', 'date_format:Y-m-d H:i:s'],
            'punches.*.status' => ['nullable', 'integer'],
            'punches.*.verify' => ['nullable', 'integer'],
        ]);

        $device = $this->device($connection, $data['device_sn'] ?? 'default');
        if (! $device->acceptsLogs()) {
            return response()->json(['ok' => false, 'message' => 'Mesin ini dinonaktifkan di panel.'], 403);
        }

        $punches = array_map(fn (array $p) => [
            'pin' => (string) $p['pin'],
            'time' => $p['time'],
            'status' => isset($p['status']) ? (int) $p['status'] : null,
            'verify' => isset($p['verify']) ? (int) $p['verify'] : null,
        ], $data['punches']);

        $created = $service->ingest($device, $punches);

        $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();
        $connection->forceFill([
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'last_log_at' => $created > 0 ? now() : $connection->last_log_at,
            'total_logs' => $connection->total_logs + $created,
        ])->save();

        return response()->json(['ok' => true, 'received' => count($punches), 'created' => $created]);
    }

    private function connection(Request $request): FingerprintNodeConnection
    {
        return $request->attributes->get('fingerprint_node');
    }

    private function touch(FingerprintNodeConnection $connection, Request $request): void
    {
        $connection->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();
    }

    /** Satu mesin virtual per (koneksi, SN mesin) supaya log tetap muncul di menu Mesin Fingerprint. */
    private function device(FingerprintNodeConnection $connection, string $sn): FingerprintDevice
    {
        $serial = substr("NODE{$connection->id}-{$sn}", 0, 60);

        return FingerprintDevice::query()->firstOrCreate(
            ['serial_number' => $serial],
            [
                'name' => "Node {$sn}",
                'location' => 'Via aplikasi Node',
                'school_id' => $connection->school_id,
                'is_active' => true,
            ]
        );
    }
}
