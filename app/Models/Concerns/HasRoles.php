<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * Role & permission untuk model autentikasi (Admin, User, StudentAccount).
 * Model wajib punya: protected string $guardName = 'admin' | 'pegawai' | 'pelajar'.
 */
trait HasRoles
{
    protected ?Collection $permissionNameCache = null;

    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles', 'model_id', 'role_id');
    }

    public function directPermissions(): MorphToMany
    {
        return $this->morphToMany(Permission::class, 'model', 'model_has_permissions', 'model_id', 'permission_id');
    }

    public function guardNameForRbac(): string
    {
        return $this->guardName;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function hasRole(string ...$names): bool
    {
        return $this->roles->pluck('name')->intersect($names)->isNotEmpty();
    }

    /** Semua nama permission (dari role + langsung), dicache per request. */
    public function allPermissionNames(): Collection
    {
        return $this->permissionNameCache ??= $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn (Role $r) => $r->permissions->pluck('name'))
            ->merge($this->directPermissions()->pluck('permissions.name'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $name): bool
    {
        return $this->allPermissionNames()->contains($name);
    }

    /** Ganti seluruh role (hanya role dengan guard yang sama). */
    public function syncRoles(array $roleIds): void
    {
        $valid = Role::where('guard_name', $this->guardNameForRbac())
            ->whereIn('id', $roleIds)->pluck('id');

        $this->roles()->sync($valid);
        $this->unsetRelation('roles');
        $this->permissionNameCache = null;
    }
}
