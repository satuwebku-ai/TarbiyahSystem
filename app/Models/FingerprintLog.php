<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintLog extends Model
{
    public const RESULTS = [
        'pending' => 'Menunggu diproses',
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'duplicate' => 'Sudah absen (diabaikan)',
        'kept_manual' => 'Data manual dipertahankan',
        'unmapped' => 'PIN belum terpetakan',
        'no_class' => 'Siswa belum punya kelas',
        'not_school_day' => 'Bukan hari sekolah',
        'holiday' => 'Hari libur',
        'too_early' => 'Sebelum jam absen dibuka',
        'too_late' => 'Setelah jam absen ditutup',
        'disabled' => 'Fingerprint dinonaktifkan',
        'bad_time' => 'Waktu ketukan tidak wajar',
    ];

    /** Hasil yang bisa membaik setelah admin memperbaiki data, sehingga layak diproses ulang. */
    public const RETRYABLE = ['pending', 'unmapped', 'no_class', 'disabled', 'too_early', 'too_late', 'not_school_day', 'holiday'];

    protected $fillable = [
        'fingerprint_device_id', 'school_id', 'pin', 'punched_at', 'status_code', 'verify_mode',
        'student_id', 'result', 'note', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['punched_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(FingerprintDevice::class, 'fingerprint_device_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result] ?? $this->result;
    }

    public function resultBadge(): string
    {
        return match ($this->result) {
            'present' => 'badge-soft-success',
            'late' => 'badge-soft-warning',
            'duplicate', 'kept_manual' => 'badge-soft-secondary',
            default => 'badge-soft-danger',
        };
    }
}
