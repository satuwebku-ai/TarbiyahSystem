<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CorrectionStatus;
use App\Events\AttendanceCorrectionReviewed;
use App\Events\AttendanceCorrectionSubmitted;
use App\Enums\EmployeeStatus;
use App\Helpers\AttendanceClock;
use App\Models\Admin;
use App\Models\User;
use App\Models\AdminAuditLog;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Jobs\MatchAttendanceFace;
use App\Services\Face\FaceMatcherFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private AttendancePolicyResolver $resolver, private AttendancePhotoGuard $photoGuard) {}

    // ---- absen sendiri -------------------------------------------------

    /**
     * @param  array{latitude?: float|string|null, longitude?: float|string|null, accuracy?: float|string|null, photo?: UploadedFile|null, source?: string}  $proof
     */
    public function checkIn(Employee $employee, ?string $ip, array $proof = []): AttendanceRecord
    {
        [$unitId, $policy, $now] = $this->requireWorkingContext($employee);
        $date = $now->toDateString();
        $geo = $this->verifyProof($policy, $proof, 'check_in');

        try {
            $saved = DB::transaction(function () use ($employee, $now, $date, $unitId, $policy, $ip, $geo, $proof) {
                $existing = AttendanceRecord::query()->where('employee_id', $employee->id)->where('attendance_date', $date)->lockForUpdate()->first();
                if ($existing) {
                    $this->fail('attendance', $existing->check_in_at
                        ? 'Anda sudah check-in hari ini.'
                        : "Hari ini sudah tercatat {$existing->status->label()}.");
                }

                [$status, $late] = $this->evaluateIn($date, $policy, $now);
                [$photo, $photoMeta] = $this->storePhoto($proof['photo'] ?? null, $date, $employee, $now, 'MASUK', 'check_in');

                try {
                    $record = AttendanceRecord::create($geo + ['check_in_photo' => $photo] + $photoMeta + [
                    'employee_id' => $employee->id,
                    'organizational_unit_id' => $unitId,
                    'attendance_date' => $date,
                    'check_in_at' => AttendanceClock::toStorage($now),
                    'status' => $status,
                    'late_minutes' => $late,
                    'scheduled_start' => $policy->work_start,
                    'scheduled_end' => $policy->work_end,
                    'source' => $proof['source'] ?? 'web',
                    'check_in_ip' => $ip,
                    ]);

                    return $record;
                } catch (\Throwable $e) {
                    $photo && Storage::disk('local')->delete($photo);
                    throw $e;
                }
            });
        } catch (UniqueConstraintViolationException) {
            $this->fail('attendance', 'Anda sudah check-in hari ini.');
        }

        $this->queueFaceMatch($saved, 'check_in');

        return $saved;
    }

    public function checkOut(Employee $employee, ?string $ip, array $proof = []): AttendanceRecord
    {
        $now = AttendanceClock::now($this->resolver->timezoneForEmployee($employee));

        $saved = DB::transaction(function () use ($employee, $now, $ip, $proof) {
            $record = AttendanceRecord::query()->where('employee_id', $employee->id)
                ->where('attendance_date', $now->toDateString())->lockForUpdate()->first();

            if (! $record || ! $record->check_in_at) {
                $this->fail('attendance', 'Anda belum check-in hari ini.');
            }
            if ($record->check_out_at) {
                $this->fail('attendance', 'Anda sudah check-out hari ini.');
            }
            if ($now->lessThanOrEqualTo($record->check_in_at)) {
                $this->fail('attendance', 'Waktu check-out harus setelah check-in.');
            }

            // Syarat lokasi/foto mengikuti kebijakan unit tempat check-in tercatat.
            $policy = $this->resolver->policyForUnit($record->organizational_unit_id);
            $geo = $this->verifyProof($policy, $proof, 'check_out');
            [$photo, $photoMeta] = $this->storePhoto($proof['photo'] ?? null, $record->attendance_date->toDateString(), $employee, $now, 'PULANG', 'check_out');

            try {
                $record->update($geo + ['check_out_photo' => $photo] + $photoMeta + ($photo ? $this->clearedPhotoReview() : []) + [
                    'check_out_at' => AttendanceClock::toStorage($now),
                    'work_minutes' => $this->minutesBetween($record->check_in_at, $now),
                    'check_out_ip' => $ip,
                ]);
            } catch (\Throwable $e) {
                $photo && Storage::disk('local')->delete($photo);
                throw $e;
            }

            return $record;
        });

        $this->queueFaceMatch($saved, 'check_out');

        return $saved;
    }

    // ---- koreksi -------------------------------------------------------

    public function submitCorrection(Employee $employee, User $actor, array $data): AttendanceCorrection
    {
        $tz = $this->resolver->timezoneForEmployee($employee);
        $date = Carbon::parse($data['attendance_date'], $tz)->startOfDay();
        $today = AttendanceClock::today($tz);
        $window = (int) config('attendance.correction_window_days');

        if ($date->gt($today) || $date->lt($today->copy()->subDays($window))) {
            $this->fail('attendance_date', "Koreksi hanya bisa diajukan untuk {$window} hari terakhir.");
        }
        if (empty($data['requested_check_in']) && empty($data['requested_check_out'])) {
            $this->fail('requested_check_in', 'Isi jam masuk dan/atau jam pulang yang benar.');
        }

        $existing = AttendanceRecord::query()->where('employee_id', $employee->id)->where('attendance_date', $date->toDateString())->first();
        if ($existing?->status->isManual()) {
            $this->fail('attendance_date', "Tanggal tersebut tercatat {$existing->status->label()} oleh pengelola.");
        }
        if (AttendanceCorrection::query()->where('employee_id', $employee->id)
            ->where('attendance_date', $date->toDateString())->where('status', CorrectionStatus::Pending)->exists()) {
            $this->fail('attendance_date', 'Sudah ada pengajuan koreksi yang menunggu untuk tanggal ini.');
        }

        $correction = AttendanceCorrection::create([
            'employee_id' => $employee->id,
            'attendance_record_id' => $existing?->id,
            'attendance_date' => $date->toDateString(),
            'requested_check_in' => $data['requested_check_in'] ?? null,
            'requested_check_out' => $data['requested_check_out'] ?? null,
            'reason' => $data['reason'],
            'status' => CorrectionStatus::Pending,
            'requested_by_user_id' => $actor->id,
        ]);

        AttendanceCorrectionSubmitted::dispatch($correction);

        return $correction;
    }

    public function reviewCorrection(AttendanceCorrection $correction, bool $approve, ?string $note, Admin $reviewer): void
    {
        DB::transaction(function () use ($correction, $approve, $note, $reviewer) {
            $correction = AttendanceCorrection::query()->whereKey($correction->id)->lockForUpdate()->firstOrFail();

            if ($correction->status !== CorrectionStatus::Pending) {
                $this->fail('correction', 'Pengajuan ini sudah diproses.');
            }
            // Pemisahan tugas terjamin oleh struktur akun: peninjau adalah admins, pemohon adalah users.

            $recordId = $approve ? $this->applyCorrection($correction)->id : $correction->attendance_record_id;

            $correction->update([
                'status' => $approve ? CorrectionStatus::Approved : CorrectionStatus::Rejected,
                'attendance_record_id' => $recordId,
                'reviewed_by_admin_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            AdminAuditLog::record($approve ? 'attendance.correction_approved' : 'attendance.correction_rejected', $reviewer, $correction->employee, [
                'correction_id' => $correction->id,
                'attendance_date' => $correction->attendance_date->toDateString(),
            ]);
        });

        AttendanceCorrectionReviewed::dispatch($correction->refresh());
    }

    private function applyCorrection(AttendanceCorrection $c): AttendanceRecord
    {
        $employee = $c->employee;
        $date = $c->attendance_date->toDateString();
        $unitId = $this->resolver->unitFor($employee, $c->attendance_date);
        $policy = $this->resolver->policyForUnit($unitId);
        if (! $unitId || ! $policy) {
            $this->fail('correction', 'Pegawai tidak memiliki penempatan atau kebijakan absensi pada tanggal tersebut.');
        }

        $record = AttendanceRecord::query()->where('employee_id', $employee->id)->where('attendance_date', $date)->lockForUpdate()->first();
        if ($record?->status->isManual()) {
            $this->fail('correction', "Tanggal tersebut tercatat {$record->status->label()}; hapus catatannya dulu.");
        }

        $tz = $policy->zone();
        $in = $c->requested_check_in ? AttendanceClock::fromLocal($date, $c->requested_check_in, $tz) : $record?->check_in_at;
        $out = $c->requested_check_out ? AttendanceClock::fromLocal($date, $c->requested_check_out, $tz) : $record?->check_out_at;
        if (! $in) {
            $this->fail('correction', 'Jam masuk belum ada; pengajuan harus menyertakan jam masuk.');
        }
        if ($out && $out->lessThanOrEqualTo($in)) {
            $this->fail('correction', 'Jam pulang harus setelah jam masuk.');
        }

        [$status, $late] = $this->evaluateIn($date, $policy, $in);
        $attributes = [
            'organizational_unit_id' => $unitId,
            'check_in_at' => AttendanceClock::toStorage($in),
            'check_out_at' => $out ? AttendanceClock::toStorage($out) : null,
            'status' => $status,
            'late_minutes' => $late,
            'work_minutes' => $out ? $this->minutesBetween($in, $out) : null,
            'scheduled_start' => $policy->work_start,
            'scheduled_end' => $policy->work_end,
            'source' => 'correction',
            'notes' => 'Koreksi: '.$c->reason,
        ];

        if ($record) {
            $record->update($attributes);

            return $record;
        }

        return AttendanceRecord::create([...$attributes, 'employee_id' => $employee->id, 'attendance_date' => $date]);
    }

    // ---- izin / sakit / cuti / tidak hadir (dicatat pengelola) -----------

    public function recordEntries(Employee $employee, AttendanceStatus $status, string $from, string $to, ?string $notes, Admin $actor): int
    {
        if (! $status->isManual()) {
            $this->fail('status', 'Status ini dicatat otomatis lewat check-in atau koreksi.');
        }
        if ($employee->status->isEnded()) {
            $this->fail('employee_id', 'Pegawai sudah tidak aktif bekerja.');
        }

        $tz = $this->resolver->timezoneForEmployee($employee);
        $start = Carbon::parse($from, $tz)->startOfDay();
        $end = Carbon::parse($to, $tz)->startOfDay();
        if ($end->lt($start)) {
            $this->fail('date_to', 'Tanggal akhir tidak boleh sebelum tanggal mulai.');
        }
        if ($start->diffInDays($end) + 1 > (int) config('attendance.max_entry_range_days')) {
            $this->fail('date_to', 'Rentang tanggal terlalu panjang.');
        }
        if ($status === AttendanceStatus::Absent && $end->gt(AttendanceClock::today($tz))) {
            $this->fail('date_to', 'Tidak hadir hanya bisa dicatat untuk hari yang sudah lewat atau hari ini.');
        }

        $assignments = $employee->assignments()->get();
        $rows = [];
        $conflicts = [];
        $existing = AttendanceRecord::query()->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('attendance_date')->map(fn ($d) => $d->toDateString())->all();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $unitId = $this->resolver->pickUnit($assignments, $date);
            $policy = $this->resolver->policyForUnit($unitId);
            if (! $unitId || ! $policy || ! $policy->isWorkDay($d->isoWeekday()) || $this->resolver->holidayFor($unitId, $date)) {
                continue; // bukan hari kerja bagi pegawai ini
            }
            if (in_array($date, $existing, true)) {
                $conflicts[] = $d->format('d M Y');
                continue;
            }
            $rows[] = ['date' => $date, 'unit' => $unitId];
        }

        if ($conflicts) {
            $this->fail('date_from', 'Sudah ada catatan absensi pada: '.implode(', ', array_slice($conflicts, 0, 3)).(count($conflicts) > 3 ? ', dst.' : '.'));
        }
        if (! $rows) {
            $this->fail('date_from', 'Tidak ada hari kerja pada rentang tanggal tersebut.');
        }

        DB::transaction(function () use ($rows, $employee, $status, $notes, $actor) {
            foreach ($rows as $row) {
                AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'organizational_unit_id' => $row['unit'],
                    'attendance_date' => $row['date'],
                    'status' => $status,
                    'source' => 'manual',
                    'notes' => $notes,
                    'created_by_admin_id' => $actor->id,
                ]);
            }
            AdminAuditLog::record('attendance.entry_created', $actor, $employee, [
                'status' => $status->value, 'from' => $rows[0]['date'], 'to' => end($rows)['date'], 'days' => count($rows),
            ]);
        });

        return count($rows);
    }

    public function deleteEntry(AttendanceRecord $record, Admin $actor): void
    {
        if (! $record->status->isManual()) {
            $this->fail('record', 'Hanya catatan izin/sakit/cuti/tidak hadir yang bisa dihapus. Gunakan koreksi untuk absensi hadir.');
        }

        DB::transaction(function () use ($record, $actor) {
            $record->delete();
            AdminAuditLog::record('attendance.entry_deleted', $actor, $record->employee, [
                'status' => $record->status->value, 'date' => $record->attendance_date->toDateString(),
            ]);
        });
    }

    // ---- bukti lokasi & foto ----------------------------------------------

    /**
     * Periksa syarat lokasi/foto dari kebijakan dan kembalikan kolom lokasi untuk disimpan.
     * Jarak dihitung di server dari koordinat yang dikirim perangkat; koordinat tetap bisa dipalsukan
     * lewat aplikasi pemalsu GPS, karena itu foto selfie ikut disimpan sebagai bukti tambahan.
     *
     * @return array<string, mixed>
     */
    private function verifyProof(?AttendancePolicy $policy, array $proof, string $prefix): array
    {
        $lat = isset($proof['latitude']) && $proof['latitude'] !== '' ? (float) $proof['latitude'] : null;
        $lng = isset($proof['longitude']) && $proof['longitude'] !== '' ? (float) $proof['longitude'] : null;
        $acc = isset($proof['accuracy']) && $proof['accuracy'] !== '' ? (int) round((float) $proof['accuracy']) : null;
        $hasPhoto = ($proof['photo'] ?? null) instanceof UploadedFile;

        if ($policy?->require_photo && ! $hasPhoto) {
            $this->fail('photo', 'Foto selfie wajib diambil untuk absen.');
        }
        if ($policy?->require_location && ($lat === null || $lng === null)) {
            $this->fail('location', 'Lokasi wajib diaktifkan. Izinkan akses lokasi di perangkat atau browser Anda, lalu coba lagi.');
        }

        $distance = null;
        if ($lat !== null && $lng !== null && $policy?->hasGeofence()) {
            $distance = (int) round($this->distanceMeters($lat, $lng, (float) $policy->geo_latitude, (float) $policy->geo_longitude));
        }

        if ($policy?->require_location) {
            $max = (int) config('attendance.max_gps_accuracy_m');
            if ($acc !== null && $acc > $max) {
                $this->fail('location', "Akurasi GPS terlalu rendah (±{$acc} m, maksimal {$max} m). Pindah ke tempat terbuka lalu coba lagi.");
            }
            if ($policy->hasGeofence() && $distance > $policy->geo_radius_m) {
                $this->fail('location', "Anda berada sekitar {$distance} m dari lokasi sekolah. Absen hanya bisa dalam radius {$policy->geo_radius_m} m.");
            }
        }

        if ($lat === null || $lng === null) {
            return [];
        }

        return [
            "{$prefix}_lat" => round($lat, 7), "{$prefix}_lng" => round($lng, 7),
            "{$prefix}_accuracy_m" => $acc, "{$prefix}_distance_m" => $distance,
        ];
    }

    /** Foto baru membuat keputusan admin sebelumnya tidak berlaku lagi. @return array<string, null> */
    private function clearedPhotoReview(): array
    {
        return ['photo_review_status' => null, 'photo_review_note' => null, 'photo_reviewed_by_admin_id' => null, 'photo_reviewed_at' => null];
    }

    /** Antrekan pencocokan wajah (setelah absen tersimpan) bila diaktifkan. Kegagalan di sini tidak boleh menggagalkan absen. */
    private function queueFaceMatch(AttendanceRecord $record, string $prefix): void
    {
        if (! $record->{"{$prefix}_photo"} || ! FaceMatcherFactory::enabled()) {
            return;
        }
        try {
            MatchAttendanceFace::dispatch($record->id, $prefix);
        } catch (\Throwable $e) {
            Log::warning('Gagal mengantrekan pencocokan wajah.', ['record_id' => $record->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Simpan foto selfie: tolak foto yang identik dengan foto absen sebelumnya, catat sidik & penanda
     * kecurigaan (AttendancePhotoGuard), lalu bubuhkan waktu server pada salinan yang disimpan.
     *
     * @return array{0: ?string, 1: array<string, ?string>} path foto dan kolom sidik/penanda untuk record
     */
    private function storePhoto(?UploadedFile $file, string $date, Employee $employee, CarbonInterface $now, string $label, string $prefix): array
    {
        if (! $file) {
            return [null, []];
        }

        $check = $this->photoGuard->inspect($file->getRealPath());

        $used = AttendanceRecord::query()
            ->where(fn ($q) => $q->where('check_in_photo_hash', $check['hash'])->orWhere('check_out_photo_hash', $check['hash']))
            ->exists();
        if ($used && config('attendance.photo_reject_duplicate')) {
            $this->fail('photo', 'Foto ini sudah pernah dipakai untuk absen. Ambil foto baru langsung dari kamera.');
        }

        $dir = "attendance-proofs/{$date}/{$employee->id}";
        $stamp = Str::ascii("{$employee->name} | {$label} | ".$now->format('Y-m-d H:i T'));
        $jpeg = config('attendance.photo_watermark') ? $this->photoGuard->stamp($file->getRealPath(), $stamp) : null;
        $path = $jpeg !== null
            ? $dir.'/'.Str::random(40).'.jpg'
            : $file->store($dir, 'local');
        if ($jpeg !== null) {
            Storage::disk('local')->put($path, $jpeg);
        }

        return [$path, [
            "{$prefix}_photo_hash" => $check['hash'],
            "{$prefix}_photo_flag" => $check['flags'] && config('attendance.photo_flag_suspicious') ? implode(',', $check['flags']) : null,
        ]];
    }

    /** Jarak lingkaran besar (haversine) dalam meter. */
    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }

    // ---- perhitungan ---------------------------------------------------

    /** @return array{0: AttendanceStatus, 1: int} status dan menit terlambat (dihitung dari jam masuk resmi). */
    public function evaluateIn(string $date, AttendancePolicy $policy, CarbonInterface $in): array
    {
        $scheduled = AttendanceClock::fromLocal($date, $policy->work_start, $policy->zone());
        $isLate = $in->gt($scheduled->copy()->addMinutes($policy->late_tolerance_minutes));

        return $isLate
            ? [AttendanceStatus::Late, (int) floor($scheduled->diffInMinutes($in))]
            : [AttendanceStatus::Present, 0];
    }

    private function minutesBetween(CarbonInterface $in, CarbonInterface $out): int
    {
        return (int) floor($in->diffInMinutes($out));
    }

    /**
     * Penempatan dan kebijakan dipilih lebih dulu (memakai zona bawaan hanya untuk menentukan penempatan),
     * lalu "sekarang" dihitung di zona kebijakan itu supaya hari kerja, libur, dan tanggal absen mengikuti zona unit.
     *
     * @return array{0: int, 1: AttendancePolicy, 2: Carbon}
     */
    private function requireWorkingContext(Employee $employee): array
    {
        if ($employee->status !== EmployeeStatus::Active) {
            $this->fail('attendance', 'Hanya pegawai berstatus Aktif yang dapat absen.');
        }

        $unitId = $this->resolver->unitFor($employee, AttendanceClock::now());
        if (! $unitId) {
            $this->fail('attendance', 'Anda belum memiliki penempatan yang berlaku hari ini.');
        }
        $policy = $this->resolver->policyForUnit($unitId);
        if (! $policy) {
            $this->fail('attendance', 'Kebijakan absensi untuk unit Anda belum diatur.');
        }

        $now = AttendanceClock::now($policy->zone());
        if (! $policy->isWorkDay($now->isoWeekday())) {
            $this->fail('attendance', 'Hari ini bukan hari kerja.');
        }
        if ($holiday = $this->resolver->holidayFor($unitId, $now->toDateString())) {
            $this->fail('attendance', "Hari ini libur: {$holiday->name}.");
        }

        return [$unitId, $policy, $now];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
