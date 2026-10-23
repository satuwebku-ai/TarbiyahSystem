<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpFoundation\IpUtils;

class FingerprintDevice extends Model
{
    /** Mesin dianggap daring bila terakhir menghubungi server dalam rentang ini. */
    public const ONLINE_WITHIN_MINUTES = 5;

    protected $fillable = [
        'school_id', 'serial_number', 'name', 'location', 'is_active', 'allowed_ips',
        'last_ip', 'last_seen_at', 'last_log_at', 'last_stamp', 'total_logs',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_log_at' => 'datetime',
            'total_logs' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FingerprintLog::class);
    }

    /** Belum diklaim sekolah: terdeteksi otomatis saat mesin pertama kali menghubungi server. */
    public function isPending(): bool
    {
        return $this->school_id === null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subMinutes(self::ONLINE_WITHIN_MINUTES));
    }

    /**
     * Pembatasan IP opsional: kosong = semua IP diterima. Berisi daftar IP/CIDR dipisah koma.
     */
    public function allowsIp(?string $ip): bool
    {
        $allowed = array_values(array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))));
        if ($allowed === []) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $allowed);
    }

    /** Sudah ada daftar IP/CIDR yang membatasi pengirim data mesin ini. */
    public function hasIpRestriction(): bool
    {
        return array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))) !== [];
    }

    /**
     * Mesin aktif tanpa batas IP ditolak bila config student_attendance.require_allowed_ips menyala.
     * Mesin nonaktif/belum diklaim tetap boleh "mendaftar" supaya admin bisa melihat & mengaturnya.
     */
    public function blockedForMissingIpRestriction(): bool
    {
        return (bool) config('student_attendance.require_allowed_ips')
            && $this->is_active
            && ! $this->hasIpRestriction();
    }

    public function acceptsLogs(): bool
    {
        return $this->is_active && $this->school_id !== null;
    }
}
