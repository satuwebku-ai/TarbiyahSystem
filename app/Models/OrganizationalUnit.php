<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalUnit extends Model
{
    protected $fillable = [
        'type_id',
        'parent_id',
        'code',
        'name',
        'description',
        'address',
        'status',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnitType::class, 'type_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(OrganizationalPosition::class, 'organizational_unit_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrganizationalAssignment::class, 'organizational_unit_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrganizationalUnitHistory::class, 'organizational_unit_id');
    }
}
