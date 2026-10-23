<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkActivity extends Model
{
    public const STATUSES = [
        'planned' => 'Direncanakan',
        'in_progress' => 'Berjalan',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'work_program_id', 'organizational_unit_id', 'code', 'title', 'description',
        'location', 'planned_start_at', 'planned_end_at', 'actual_start_at',
        'actual_end_at', 'status', 'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(WorkProgram::class, 'work_program_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class)->orderBy('participant_name');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ActivityDocument::class)->latest();
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(ActivityBudgetItem::class)->orderBy('name');
    }

    public function report(): HasOne
    {
        return $this->hasOne(ActivityReport::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ActivityApproval::class)->whereNotNull('work_activity_id')->latest('requested_at');
    }
}
