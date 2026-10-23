<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUnitAccess extends Model
{
    protected $table = 'user_unit_access';

    public $timestamps = false;

    protected $fillable = ['organizational_unit_id', 'model_type', 'model_id', 'created_at'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }
}
