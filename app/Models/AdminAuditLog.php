<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_admin_id',
        'actor_name',
        'event',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }

    public static function record(
        string $event,
        ?Authenticatable $actor = null,
        ?Model $subject = null,
        array $details = [],
    ): void {
        $request = request();

        // Pelaku non-admin (pegawai di portal pegawai) tidak punya baris di admins:
        // identitasnya dicatat pada nama dan rincian, bukan pada kolom actor_admin_id.
        if ($actor && ! $actor instanceof Admin) {
            $details += ['actor' => ['type' => class_basename($actor), 'id' => $actor->getAuthIdentifier()]];
        }

        static::create([
            'actor_admin_id' => $actor instanceof Admin ? $actor->getKey() : null,
            'actor_name' => $actor?->name,
            'event' => $event,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'details' => $details ?: null,
            'created_at' => now(),
        ]);
    }
}
