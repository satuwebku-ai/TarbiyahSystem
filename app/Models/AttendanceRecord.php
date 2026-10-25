<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Services\AttendancePhotoGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id', 'organizational_unit_id', 'attendance_date', 'check_in_at', 'check_out_at',
        'status', 'late_minutes', 'work_minutes', 'scheduled_start', 'scheduled_end', 'source',
        'check_in_ip', 'check_out_ip', 'notes', 'created_by_admin_id',
        'check_in_lat', 'check_in_lng', 'check_in_accuracy_m', 'check_in_distance_m', 'check_in_photo', 'check_in_photo_hash', 'check_in_photo_flag',
        'check_out_lat', 'check_out_lng', 'check_out_accuracy_m', 'check_out_distance_m', 'check_out_photo', 'check_out_photo_hash', 'check_out_photo_flag',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'work_minutes' => 'integer',
        ];
    }

    /** Catatan kecurigaan foto (masuk/pulang) dalam bahasa admin; kosong bila tidak ada. @return list<string> */
    public function photoNotes(): array
    {
        $notes = [];
        foreach (['check_in_photo_flag' => 'Foto masuk', 'check_out_photo_flag' => 'Foto pulang'] as $col => $label) {
            foreach (array_filter(explode(',', (string) $this->{$col})) as $flag) {
                $notes[] = $label.' '.(AttendancePhotoGuard::FLAG_LABELS[$flag] ?? $flag);
            }
        }

        return $notes;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }
}
