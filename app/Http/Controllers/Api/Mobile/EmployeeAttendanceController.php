<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\EmployeeStatus;
use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Services\AttendancePolicyResolver;
use App\Services\AttendanceRecapService;
use App\Services\AttendanceService;
use App\Services\PegawaiPortalAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Absen & koreksi pegawai lewat aplikasi mobile. Memakai AttendanceService yang sama dengan portal web,
 * jadi semua aturan (kebijakan unit, geofence, foto, hari libur, jendela koreksi) otomatis berlaku.
 * Pegawai ditentukan dari token — tidak ada parameter id dari request.
 */
class EmployeeAttendanceController extends Controller
{
    private const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

    private const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function dashboard(Request $request, PegawaiPortalAccess $access, AttendancePolicyResolver $resolver): JsonResponse
    {
        $employee = $this->employee($request, $access);
        $unitId = $resolver->unitFor($employee, AttendanceClock::now());
        $policy = $unitId ? $resolver->policyForUnit($unitId) : null;
        $tz = $resolver->timezoneFor($policy);
        $now = AttendanceClock::now($tz);
        $holiday = $unitId ? $resolver->holidayFor($unitId, $now->toDateString()) : null;

        $today = AttendanceRecord::query()
            ->where('employee_id', $employee->id)->where('attendance_date', $now->toDateString())->first();
        $recent = AttendanceRecord::query()
            ->where('employee_id', $employee->id)->orderByDesc('attendance_date')->limit(7)->get();

        return response()->json([
            'unit' => $unitId ? OrganizationalUnit::query()->whereKey($unitId)->value('name') : null,
            'timezone' => $tz,
            'serverTime' => $now->toIso8601String(),
            'holiday' => $holiday ? ['name' => $holiday->name] : null,
            'needsPhoto' => (bool) $policy?->require_photo,
            'needsLocation' => (bool) $policy?->require_location,
            // Aturan kebijakan untuk ditampilkan saja; keputusan tetap diambil server. Titik koordinat sekolah sengaja tidak dikirim.
            'workStart' => $policy?->work_start ? substr((string) $policy->work_start, 0, 5) : null,
            'workEnd' => $policy?->work_end ? substr((string) $policy->work_end, 0, 5) : null,
            'lateToleranceMinutes' => $policy?->late_tolerance_minutes,
            'checkinDeadline' => $policy?->checkinDeadlineLabel(),
            'checkinClosed' => (bool) $policy?->checkinClosed($now),
            'geofenceRadiusM' => $policy?->require_location && $policy->hasGeofence() ? $policy->geo_radius_m : null,
            'canCheckIn' => (bool) (! $today && $policy && ! $holiday
                && $policy->isWorkDay($now->isoWeekday()) && ! $policy->checkinClosed($now) && $employee->status === EmployeeStatus::Active),
            'canCheckOut' => (bool) ($today && $today->check_in_at && ! $today->check_out_at),
            'today' => $today ? $this->recordPayload($today, $tz) : null,
            'recent' => $recent->map(fn ($r) => $this->recordPayload($r, $tz))->values(),
        ]);
    }

    public function history(Request $request, PegawaiPortalAccess $access, AttendancePolicyResolver $resolver): JsonResponse
    {
        $employee = $this->employee($request, $access);
        $month = $this->resolveMonth($request, $resolver->timezoneForEmployee($employee));
        $tz = $resolver->timezoneForEmployee($employee);

        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('attendance_date')
            ->get();

        return response()->json([
            'month' => $month->format('Y-m'),
            'records' => $records->map(fn ($r) => $this->recordPayload($r, $tz))->values(),
        ]);
    }

    /**
     * Rekap bulanan pegawai yang sedang login. Memakai AttendanceRecapService yang sama dengan laporan bulanan web,
     * jadi "tidak hadir otomatis" (hari kerja lewat tanpa catatan), hari libur, dan hari bukan kerja dihitung sama.
     * `percentage` = (hadir + terlambat) / semua hari yang terhitung; null bila belum ada hari terhitung.
     */
    public function summary(Request $request, PegawaiPortalAccess $access, AttendancePolicyResolver $resolver, AttendanceRecapService $recap): JsonResponse
    {
        $employee = $this->employee($request, $access);
        $month = $this->resolveMonth($request, $resolver->timezoneForEmployee($employee));

        $result = $recap->build($month->copy()->startOfMonth(), $month->copy()->endOfMonth()->startOfDay(), collect([$employee]));
        $row = $result['rows']->first();

        $counts = $row['counts'] ?? array_fill_keys(AttendanceRecapService::COUNT_KEYS, 0);
        $total = array_sum($counts);

        return response()->json([
            'month' => $month->format('Y-m'),
            'monthLabel' => self::MONTHS[$month->month].' '.$month->year,
            'total' => $total,
            'percentage' => $total > 0 ? round(($counts['present'] + $counts['late']) / $total * 100, 1) : null,
            'counts' => $counts,
            'workMinutes' => (int) ($row['work_minutes'] ?? 0),
            'lateMinutes' => (int) ($row['late_minutes'] ?? 0),
        ]);
    }

    public function corrections(Request $request, PegawaiPortalAccess $access): JsonResponse
    {
        $employee = $this->employee($request, $access);

        return response()->json(
            AttendanceCorrection::query()->where('employee_id', $employee->id)->latest('id')->limit(30)->get()
                ->map(fn ($c) => $this->correctionPayload($c))->values()
        );
    }

    public function storeCorrection(Request $request, PegawaiPortalAccess $access, AttendanceService $service): JsonResponse
    {
        $data = $request->validate([
            'attendanceDate' => ['required', 'date_format:Y-m-d'],
            'requestedCheckIn' => ['nullable', 'date_format:H:i'],
            'requestedCheckOut' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $correction = $service->submitCorrection($this->employee($request, $access), $request->user(), [
            'attendance_date' => $data['attendanceDate'],
            'requested_check_in' => $data['requestedCheckIn'] ?? null,
            'requested_check_out' => $data['requestedCheckOut'] ?? null,
            'reason' => $data['reason'],
        ]);

        return response()->json($this->correctionPayload($correction), 201);
    }

    public function checkIn(Request $request, PegawaiPortalAccess $access, AttendanceService $service): JsonResponse
    {
        return $this->withProof($request, function (array $proof) use ($request, $access, $service) {
            $record = $service->checkIn($this->employee($request, $access), $request->ip(), $proof + ['source' => 'mobile']);

            return [$record, AttendanceClock::forUnit($record->organizational_unit_id), 'Check-in tercatat.'];
        });
    }

    public function checkOut(Request $request, PegawaiPortalAccess $access, AttendanceService $service): JsonResponse
    {
        return $this->withProof($request, function (array $proof) use ($request, $access, $service) {
            $record = $service->checkOut($this->employee($request, $access), $request->ip(), $proof);

            return [$record, AttendanceClock::forUnit($record->organizational_unit_id), 'Check-out tercatat.'];
        });
    }

    /**
     * Validasi bukti absen (lokasi + foto base64), ubah foto menjadi file sementara bernilai UploadedFile
     * (yang dibutuhkan AttendanceService), jalankan aksi, lalu hapus file sementara apa pun hasilnya.
     *
     * @param  callable(array): array{0: AttendanceRecord, 1: string, 2: string}  $action
     */
    private function withProof(Request $request, callable $action): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'photoBase64' => ['nullable', 'string', 'max:3500000'],
        ]);

        $tmp = null;
        $proof = [
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy' => $data['accuracy'] ?? null,
            'photo' => null,
        ];

        try {
            if (! empty($data['photoBase64'])) {
                [$proof['photo'], $tmp] = $this->photoFromBase64($data['photoBase64']);
            }

            [$record, $tz, $message] = $action($proof);
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return response()->json([
            'message' => $message,
            'record' => $this->recordPayload($record, $tz),
        ]);
    }

    /** @return array{0: UploadedFile, 1: string} [file, path sementara] */
    private function photoFromBase64(string $encoded): array
    {
        $encoded = preg_replace('/^data:[^;]+;base64,/', '', trim($encoded));
        $binary = base64_decode((string) $encoded, true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages(['photo' => 'Foto tidak valid. Ambil ulang foto bukti.']);
        }
        if (strlen($binary) > self::MAX_PHOTO_BYTES) {
            throw ValidationException::withMessages(['photo' => 'Ukuran foto maksimal 2 MB.']);
        }

        // Jenis file ditentukan dari isinya, bukan dari klaim klien.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: '';
        if (! isset(self::PHOTO_TYPES[$mime]) || @getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages(['photo' => 'Foto harus berformat JPEG, PNG, atau WebP.']);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($tmp, $binary);

        return [new UploadedFile($tmp, 'bukti.'.self::PHOTO_TYPES[$mime], $mime, null, true), $tmp];
    }

    private function recordPayload(AttendanceRecord $record, string $tz): array
    {
        return [
            'id' => (string) $record->id,
            'date' => $record->attendance_date->toDateString(),
            'checkInAt' => AttendanceClock::toLocal($record->check_in_at, $tz)?->toIso8601String(),
            'checkOutAt' => AttendanceClock::toLocal($record->check_out_at, $tz)?->toIso8601String(),
            'latitude' => $record->check_in_lat !== null ? (float) $record->check_in_lat : null,
            'longitude' => $record->check_in_lng !== null ? (float) $record->check_in_lng : null,
            'status' => $record->status->value,
            'statusLabel' => $record->status->label(),
            'lateMinutes' => $record->late_minutes,
            'workMinutes' => $record->work_minutes,
        ];
    }

    private function correctionPayload(AttendanceCorrection $c): array
    {
        $time = fn (?string $v) => $v ? substr($v, 0, 5) : null;

        return [
            'id' => (string) $c->id,
            'attendanceDate' => $c->attendance_date->toDateString(),
            'requestedCheckIn' => $time($c->requested_check_in),
            'requestedCheckOut' => $time($c->requested_check_out),
            'reason' => $c->reason,
            'status' => $c->status->label(),
            'statusValue' => $c->status->value,
            'reviewNote' => $c->review_note,
            'createdAt' => $c->created_at?->toIso8601String(),
        ];
    }

    /** Bulan dari ?month=YYYY-MM (awal bulan, zona waktu unit); kosong atau melewati bulan ini = bulan ini. */
    private function resolveMonth(Request $request, string $tz): Carbon
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $thisMonth = AttendanceClock::today($tz)->startOfMonth();
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m-d', $request->string('month').'-01', $tz)->startOfDay()
            : $thisMonth;

        return $month->gt($thisMonth) ? $thisMonth : $month;
    }

    private function employee(Request $request, PegawaiPortalAccess $access): Employee
    {
        return $access->employeeFor($request->user()) ?? abort(403, 'Akun belum dihubungkan ke data pegawai.');
    }
}
