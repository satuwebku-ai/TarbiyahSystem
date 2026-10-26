<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFaceReference extends Model
{
    protected $fillable = ['employee_id', 'path', 'uploaded_by_admin_id', 'consent_confirmed_at'];

    protected function casts(): array
    {
        return ['consent_confirmed_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'uploaded_by_admin_id');
    }
}
