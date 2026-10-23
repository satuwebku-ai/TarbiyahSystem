<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAssignment extends Model
{
    protected $fillable = [
        'employee_id', 'organizational_unit_id', 'organizational_position_id',
        'is_primary', 'start_date', 'end_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(OrganizationalPosition::class, 'organizational_position_id');
    }
}
