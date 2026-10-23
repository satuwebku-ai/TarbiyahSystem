<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'guard_name', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function admins(): MorphToMany
    {
        return $this->morphedByMany(Admin::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }

    public function studentAccounts(): MorphToMany
    {
        return $this->morphedByMany(StudentAccount::class, 'model', 'model_has_roles', 'role_id', 'model_id');
    }
}
