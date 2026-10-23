<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityBudgetItem extends Model
{
    protected $fillable = [
        'work_activity_id', 'name', 'description', 'planned_amount', 'realized_amount',
    ];

    protected function casts(): array
    {
        return [
            'planned_amount' => 'decimal:2',
            'realized_amount' => 'decimal:2',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(WorkActivity::class, 'work_activity_id');
    }
}
