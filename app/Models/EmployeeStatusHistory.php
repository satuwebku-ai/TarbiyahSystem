<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['employee_id', 'from_status', 'to_status', 'effective_date', 'reason', 'actor_admin_id', 'created_at'];

    protected function casts(): array
    {
        return ['effective_date' => 'date:Y-m-d', 'created_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }
}
