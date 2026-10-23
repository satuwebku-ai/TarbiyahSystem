<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalPosition extends Model
{
    protected $fillable = ['organizational_unit_id', 'name', 'description', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity' => 'integer'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrganizationalAssignment::class, 'organizational_position_id');
    }

    /** Satu orang saja (bukan tanpa batas dan bukan banyak). */
    public function isSingleHolder(): bool
    {
        return $this->capacity === 1;
    }

    public function capacityLabel(): string
    {
        return $this->capacity === null ? 'Tanpa batas' : ($this->capacity === 1 ? '1 orang' : $this->capacity.' orang');
    }

    /** Apakah jabatan sudah penuh pada rentang tanggal tertentu (kapasitas NULL = tidak pernah penuh). */
    public function isFull(string $startDate, ?string $endDate, ?int $ignoreAssignmentId = null): bool
    {
        if ($this->capacity === null) {
            return false;
        }
        $end = $endDate ?: '9999-12-31';

        return $this->assignments()
            ->where('start_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $startDate))
            ->when($ignoreAssignmentId, fn ($q) => $q->whereKeyNot($ignoreAssignmentId))
            ->count() >= $this->capacity;
    }
}
