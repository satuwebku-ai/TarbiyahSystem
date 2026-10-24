<?php

namespace App\Services;

use App\Helpers\AttendanceClock;
use App\Models\AttendanceHoliday;
use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Menentukan unit, kebijakan, dan hari libur yang berlaku bagi pegawai pada suatu tanggal.
 * Kebijakan dicari dari unit pegawai naik ke unit induk, lalu kebijakan umum (unit = null).
 */
class AttendancePolicyResolver
{
    /** @var array<int, int[]> */
    private array $chains = [];

    private ?Collection $policies = null;

    /** @return int[] unit itu sendiri lalu induk-induknya. */
    public function ancestorIds(int $unitId): array
    {
        if (isset($this->chains[$unitId])) {
            return $this->chains[$unitId];
        }

        $ids = [];
        $current = $unitId;
        while ($current && ! in_array($current, $ids, true) && count($ids) < 25) {
            $ids[] = $current;
            $current = (int) OrganizationalUnit::query()->whereKey($current)->value('parent_id');
        }

        return $this->chains[$unitId] = $ids;
    }

    /** Pilih penempatan yang berlaku pada tanggal itu; yang utama didahulukan. */
    public function pickUnit(Collection $assignments, string $date): ?int
    {
        return $assignments
            ->filter(fn ($a) => $a->start_date->toDateString() <= $date && ($a->end_date === null || $a->end_date->toDateString() >= $date))
            ->sortBy([['is_primary', 'desc'], ['start_date', 'asc']])
            ->first()?->organizational_unit_id;
    }

    public function unitFor(Employee $employee, CarbonInterface $date): ?int
    {
        return $this->pickUnit($employee->assignments()->get(), $date->toDateString());
    }

    public function policyForUnit(?int $unitId): ?AttendancePolicy
    {
        $this->policies ??= AttendancePolicy::query()->where('is_active', true)->get()
            ->keyBy(fn ($p) => $p->organizational_unit_id ?? 'global');

        foreach ($unitId ? $this->ancestorIds($unitId) : [] as $id) {
            if ($this->policies->has($id)) {
                return $this->policies->get($id);
            }
        }

        return $this->policies->get('global');
    }

    /** Zona waktu efektif sebuah kebijakan (null = zona bawaan sistem). */
    public function timezoneFor(?AttendancePolicy $policy): string
    {
        return $policy ? $policy->zone() : AttendanceClock::tz();
    }

    /** Zona waktu yang berlaku bagi unit, mengikuti kebijakan yang berlaku pada unit itu. */
    public function timezoneForUnit(?int $unitId): string
    {
        return $this->timezoneFor($this->policyForUnit($unitId));
    }

    /** Zona waktu pegawai berdasarkan penempatan yang berlaku pada $at (zona bawaan dipakai untuk memilih penempatan). */
    public function timezoneForEmployee(Employee $employee, ?CarbonInterface $at = null): string
    {
        return $this->timezoneForUnit($this->unitFor($employee, $at ?? AttendanceClock::now()));
    }

    public function holidayFor(?int $unitId, string $date): ?AttendanceHoliday
    {
        $ids = $unitId ? $this->ancestorIds($unitId) : [];

        return AttendanceHoliday::query()
            ->where('holiday_date', $date)
            ->where(fn ($q) => $q->whereNull('organizational_unit_id')->orWhereIn('organizational_unit_id', $ids))
            ->first();
    }
}
