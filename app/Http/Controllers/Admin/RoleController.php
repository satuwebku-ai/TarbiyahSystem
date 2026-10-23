<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\AdminAuditLog;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class RoleController extends Controller implements HasMiddleware
{
    private const GUARD = 'admin';

    public static function middleware(): array
    {
        return [
            new Middleware('can:roles.view', only: ['index']),
            new Middleware('can:roles.create', only: ['create', 'store']),
            new Middleware('can:roles.update', only: ['edit', 'update']),
            new Middleware('can:roles.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $this->authorizeRoleAdministration();
        $roles = Role::where('guard_name', self::GUARD)
            ->withCount(['permissions', 'admins'])
            ->orderBy('id')->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $this->authorizeRoleAdministration();
        return view('admin.roles.form', [
            'role'        => new Role(),
            'groups'      => $this->permissionGroups(),
            'selected'    => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRoleAdministration();
        $data = $request->validate([
            'name'          => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:60',
                                Rule::unique('roles', 'name')->where('guard_name', self::GUARD)],
            'label'         => ['required', 'string', 'max:100'],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'id')->where('guard_name', self::GUARD)],
        ]);

        $role = Role::create([
            'name'        => $data['name'],
            'label'       => $data['label'],
            'description' => $data['description'] ?? null,
            'guard_name'  => self::GUARD,
        ]);
        $role->permissions()->sync($data['permissions'] ?? []);
        AdminAuditLog::record('role.created', $request->user(), $role);

        return redirect()->route('admin.roles.index')->with('success', 'Role berhasil dibuat.');
    }

    public function edit(Role $role)
    {
        $this->authorizeRoleAdministration();
        $this->ensureAdminRole($role);

        return view('admin.roles.form', [
            'role'     => $role,
            'groups'   => $this->permissionGroups(),
            'selected' => $role->permissions()->pluck('permissions.id')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAdministration();
        $this->ensureAdminRole($role);

        $data = $request->validate([
            'label'         => ['required', 'string', 'max:100'],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'id')->where('guard_name', self::GUARD)],
        ]);

        $role->update(['label' => $data['label'], 'description' => $data['description'] ?? null]);

        // Super Admin selalu punya akses penuh; daftar permission-nya tidak diubah.
        if ($role->name !== 'super-admin') {
            $role->permissions()->sync($data['permissions'] ?? []);
        }
        AdminAuditLog::record('role.updated', $request->user(), $role);

        return redirect()->route('admin.roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorizeRoleAdministration();
        $this->ensureAdminRole($role);

        if ($role->is_system) {
            return back()->with('error', 'Role sistem tidak dapat dihapus.');
        }
        if ($role->admins()->exists()) {
            return back()->with('error', 'Role masih dipakai akun pengelola. Lepas dulu dari akun tersebut.');
        }

        AdminAuditLog::record('role.deleted', request()->user(), $role);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role dihapus.');
    }

    private function ensureAdminRole(Role $role): void
    {
        abort_unless($role->guard_name === self::GUARD, 404);

        if (in_array($role->name, ['pengurus-besar', 'komisaris-wilayah', 'komisaris-daerah'], true)) {
            $this->authorizeRoleAdministration();
        }
    }

    /** Role dan permission adalah batas keamanan; pengelola biasa tidak boleh
     * mengubahnya untuk memberi dirinya hak akses yang lebih tinggi. */
    private function authorizeRoleAdministration(): void
    {
        abort_unless(request()->user('admin')?->isSuperAdmin(), 403, 'Hanya Super Admin yang dapat mengelola role dan permission.');
    }

    private function permissionGroups()
    {
        return Permission::where('guard_name', self::GUARD)->orderBy('group')->orderBy('id')->get()->groupBy('group');
    }
}
