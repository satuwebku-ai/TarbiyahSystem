<?php

namespace App\Services;

use App\Helpers\AttendanceClock;
use App\Models\AttendanceRecord;
use App\Models\EmployeeAssignment;
use App\Models\OrganizationalUnit;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Rekap absensi per pegawai dan per unit.
 * "Tidak hadir otomatis" = hari kerja yang sudah lewat tanpa catatan apa pun.
 * Hari ini yang belum ada catatan belum dihitung sebagai tidak hadir.
 */
class AttendanceRecapService
{
    public const COUNT_KEYS = ['present', 'late', 'permit', 'sick', 'leave', 'absent'];

    private const DERIVED = [
        'holiday' => ['Libur', 'badge-soft-secondary'],
        'off' => ['Bukan hari kerja', 'badge-soft-secondary'],
        'none' => ['—', 'badge-soft-secondary'],
        'absent_auto' => ['Tidak hadir', 'badge-soft-danger'],
    ];

    public function __construct(private AttendancePolicyResolver $resolver) {}

    public static function label(string $key): string
    {
        return self::DERIVED[$key][0] ?? \App\Enums\AttendanceStatus::from($key)->label();
    }

    public static function badge(string $key): string
    {
        return self::DERIVED[$key][1] ?? \App\Enums\AttendanceStatus::from($key)->badge();
    }

    /**
     * @param  Collection  $employees  Employee models
     * @param  int[]|null  $unitScope  hanya penempatan di unit ini yang dihitung (null = semua)
     * @return array{rows: Collection, units: Collection, from: Carbon, to: Carbon}
     */
    public function build(Carbon $from, Carbon $to, Collection $employees, ?array $unitScope = null): array
    {
        $ids = $employees->pluck('id');
        $fromS = $from->toDateString();
        $toS = $to->toDateString();
        $today = AttendanceClock::today()->toDateString();

        $assignments = EmployeeAssignment::query()
            ->whereIn('employee_id', $ids)
            ->where('start_date', '<=', $toS)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $fromS))
            ->when($unitScope !== null, fn ($q) => $q->whereIn('organizational_unit_id', $unitScope))
            ->get()->groupBy('employee_id');

        $records = AttendanceRecord::query()
            ->whereIn('employee_id', $ids)
            ->whereBetween('attendance_date', [$fromS, $toS])
            ->get()->groupBy('employee_id')
            ->map(fn ($g) => $g->keyBy(fn ($r) => $r->attendance_date->toDateString()));

        $unitTotals = [];
        $rows = $employees->map(function ($employee) use ($from, $to, $today, $assignments, $records, $unitScope, &$unitTotals) {
            $mine = $assignments->get($employee->id, collect());
            $mineRecords = $records->get($employee->id, collect());
            $counts = array_fill_keys(self::COUNT_KEYS, 0);
            $work = 0;
            $lateMinutes = 0;
            $days = [];

            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $date = $d->toDateString();
                $record = $mineRecords->get($date);
                $unitId = $this->resolver->pickUnit($mine, $date);
                if ($record && $unitScope !== null && ! in_array($record->organizational_unit_id, $unitScope, true)) {
                    $record = null; // catatan dari unit di luar cakupan tidak ikut
                    $unitId = null;
                }
                if (! $unitId && ! $record) {
                    continue; // pegawai tidak bertugas pada hari itu
                }
                $unitId = $record?->organizational_unit_id ?? $unitId;

                if ($record) {
                    $key = $record->status->value;
                    $work += (int) $record->work_minutes;
                    $lateMinutes += (int) $record->late_minutes;
                } else {
                    $policy = $this->resolver->policyForUnit($unitId);
                    $key = match (true) {
                        ! $policy => 'none',
                        ! $policy->isWorkDay($d->isoWeekday()) => 'off',
                        (bool) $this->resolver->holidayFor($unitId, $date) => 'holiday',
                        $date >= $today => 'none',
                        default => 'absent_auto',
                    };
                }

                $bucket = $key === 'absent_auto' ? 'absent' : $key;
                if (isset($counts[$bucket])) {
                    $counts[$bucket]++;
                    $unitTotals[$unitId] ??= array_fill_keys(self::COUNT_KEYS, 0);
                    $unitTotals[$unitId][$bucket]++;
                }
                $days[$date] = ['status' => $key, 'record' => $record, 'unit_id' => $unitId];
            }

            return compact('employee', 'days', 'counts') + ['work_minutes' => $work, 'late_minutes' => $lateMinutes];
        })->filter(fn ($row) => $row['days'] !== [])->values();

        $names = OrganizationalUnit::query()->whereIn('id', array_keys($unitTotals))->pluck('name', 'id');
        $units = collect($unitTotals)->map(fn ($c, $id) => ['unit' => $names[$id] ?? "#$id", 'counts' => $c])
            ->sortBy('unit')->values();

        return ['rows' => $rows, 'units' => $units, 'from' => $from, 'to' => $to];
    }
}
