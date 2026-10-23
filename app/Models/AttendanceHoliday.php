<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceHoliday extends Model
{
    protected $fillable = ['holiday_date', 'name', 'organizational_unit_id'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date:Y-m-d'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }
}
