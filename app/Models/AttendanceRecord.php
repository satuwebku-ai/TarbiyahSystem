<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Services\AttendancePhotoGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id', 'organizational_unit_id', 'attendance_date', 'check_in_at', 'check_out_at',
        'status', 'late_minutes', 'work_minutes', 'scheduled_start', 'scheduled_end', 'source',
        'check_in_ip', 'check_out_ip', 'notes', 'created_by_admin_id',
        'check_in_lat', 'check_in_lng', 'check_in_accuracy_m', 'check_in_distance_m', 'check_in_photo', 'check_in_photo_hash', 'check_in_photo_flag', 'check_in_face_score', 'check_in_face_status',
        'check_out_lat', 'check_out_lng', 'check_out_accuracy_m', 'check_out_distance_m', 'check_out_photo', 'check_out_photo_hash', 'check_out_photo_flag', 'check_out_face_score', 'check_out_face_status',
        'photo_review_status', 'photo_review_note', 'photo_reviewed_by_admin_id', 'photo_reviewed_at',
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

    /** Lencana hasil pencocokan wajah (masuk/pulang) untuk laporan admin. @return list<array{text: string, class: string}> */
    public function faceBadges(): array
    {
        $map = [
            'match' => ['Wajah cocok', 'success'],
            'review' => ['Wajah perlu dicek', 'warning'],
            'mismatch' => ['Wajah tidak cocok', 'danger'],
            'no_face' => ['Wajah tidak terdeteksi', 'warning'],
            'no_reference' => ['Belum ada foto acuan', 'secondary'],
            'error' => ['Pencocokan gagal', 'secondary'],
        ];
        $badges = [];
        foreach (['check_in' => 'Masuk', 'check_out' => 'Pulang'] as $prefix => $label) {
            $status = $this->{"{$prefix}_face_status"};
            if (! $status || ! isset($map[$status])) {
                continue;
            }
            $score = $this->{"{$prefix}_face_score"};
            $badges[] = [
                'text' => "{$label}: {$map[$status][0]}".($score !== null ? ' '.rtrim(rtrim(number_format((float) $score, 1, '.', ''), '0'), '.').'%' : ''),
                'class' => $map[$status][1],
            ];
        }

        return $badges;
    }

    public const REVIEW_OK = 'ok';

    public const REVIEW_SUSPICIOUS = 'suspicious';

    /** Absen berfoto yang punya penanda (ciri galeri atau wajah meragukan) dan belum diputuskan admin. */
    public function scopeNeedsPhotoReview(Builder $query): Builder
    {
        $doubtful = ['review', 'mismatch', 'no_face'];

        return $query->whereNull('photo_review_status')
            ->where(fn ($w) => $w->whereNotNull('check_in_photo')->orWhereNotNull('check_out_photo'))
            ->where(fn ($w) => $w->whereNotNull('check_in_photo_flag')->orWhereNotNull('check_out_photo_flag')
                ->orWhereIn('check_in_face_status', $doubtful)->orWhereIn('check_out_face_status', $doubtful));
    }

    /** Lencana keputusan admin untuk laporan harian, atau null bila belum diputuskan. @return array{text: string, class: string, title: string}|null */
    public function reviewBadge(): ?array
    {
        return match ($this->photo_review_status) {
            self::REVIEW_OK => ['text' => '✔ Foto diverifikasi', 'class' => 'success', 'title' => (string) $this->photo_review_note],
            self::REVIEW_SUSPICIOUS => ['text' => '✖ Foto mencurigakan', 'class' => 'danger', 'title' => (string) $this->photo_review_note],
            default => null,
        };
    }

    public function photoReviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'photo_reviewed_by_admin_id');
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
