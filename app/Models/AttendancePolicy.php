<?php

namespace App\Models;

use App\Helpers\AttendanceClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePolicy extends Model
{
    public const DAY_NAMES = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    protected $fillable = ['organizational_unit_id', 'name', 'work_days', 'work_start', 'work_end', 'late_tolerance_minutes', 'checkin_deadline', 'timezone', 'is_active',
        'require_location', 'geo_latitude', 'geo_longitude', 'geo_radius_m', 'require_photo'];

    protected function casts(): array
    {
        return [
            'work_days' => 'array', 'is_active' => 'boolean', 'late_tolerance_minutes' => 'integer',
            'require_location' => 'boolean', 'require_photo' => 'boolean', 'geo_radius_m' => 'integer',
            'geo_latitude' => 'float', 'geo_longitude' => 'float',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    /** Zona waktu efektif kebijakan ini; kosong = zona bawaan sistem. */
    public function zone(): string
    {
        return AttendanceClock::tz($this->timezone);
    }

    /** Jam batas check-in "HH:mm" (kosong = tanpa batas). */
    public function checkinDeadlineLabel(): ?string
    {
        return $this->checkin_deadline ? substr((string) $this->checkin_deadline, 0, 5) : null;
    }

    /** Batas check-in pagi sudah lewat pada waktu $now (zona kebijakan); batas berlaku sampai akhir menitnya. */
    public function checkinClosed(\Carbon\Carbon $now): bool
    {
        if (! $this->checkin_deadline) {
            return false;
        }
        $limit = AttendanceClock::fromLocal($now->toDateString(), $this->checkin_deadline, $this->zone());

        return $now->gte($limit->copy()->addMinute());
    }

    public function hasGeofence(): bool
    {
        return $this->geo_latitude !== null && $this->geo_longitude !== null;
    }

    public function isWorkDay(int $isoWeekday): bool
    {
        return in_array($isoWeekday, array_map('intval', $this->work_days ?? []), true);
    }

    public function dayLabels(): string
    {
        return collect($this->work_days ?? [])->sort()->map(fn ($d) => self::DAY_NAMES[(int) $d] ?? $d)->implode(', ');
    }
}
