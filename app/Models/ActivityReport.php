<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReport extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Dikirim untuk evaluasi',
        'revision_requested' => 'Perlu perbaikan',
        'reviewed' => 'Dievaluasi',
    ];

    protected $fillable = [
        'work_activity_id', 'summary', 'outcomes', 'challenges', 'recommendations',
        'status', 'submitted_at', 'submitted_by_admin_id', 'evaluated_by_admin_id',
        'evaluated_at', 'evaluation_note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'evaluated_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'work_activity_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'submitted_by_admin_id');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'evaluated_by_admin_id');
    }
}
