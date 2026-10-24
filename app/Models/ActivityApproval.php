<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityApproval extends Model
{
    protected $fillable = [
        'work_program_id', 'work_activity_id', 'status', 'note',
        'requested_by_admin_id', 'reviewed_by_admin_id', 'requested_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(WorkProgram::class, 'work_program_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'work_activity_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
