<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkProgram extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Perlu revisi',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'organizational_unit_id', 'code', 'title', 'description', 'objectives',
        'start_date', 'end_date', 'estimated_budget', 'status', 'submitted_at',
        'approved_at', 'rejected_at', 'created_by_admin_id', 'approved_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'estimated_budget' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkActivity::class)->orderBy('planned_start_at')->orderBy('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ActivityApproval::class)->whereNull('work_activity_id')->latest('requested_at');
    }
}
