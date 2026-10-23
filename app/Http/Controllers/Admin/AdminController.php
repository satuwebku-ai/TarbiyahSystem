<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UserUnitAccess;
use App\Services\EmployeeAccess;
use App\Rules\AdminPasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Kelola akun pengelola + penetapan role. */
class AdminController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:admins.view', only: ['index']),
            new Middleware('can:admins.create', only: ['create', 'store']),
            new Middleware('can:admins.update', only: ['edit', 'update']),
            new Middleware('can:admins.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $admins = Admin::with('roles')->orderBy('id')->paginate(15);

        // Nama unit yang ditetapkan per akun (kolom "Cakupan unit" di daftar).
        $unitNames = UserUnitAccess::query()
            ->where('model_type', Admin::class)
            ->whereIn('model_id', $admins->pluck('id'))
            ->with('unit:id,name')
            ->get()
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('unit.name')->filter()->sort()->values()->all());

        return view('admin.admins.index', ['admins' => $admins, 'unitNames' => $unitNames]);
    }

    public function create()
    {
        return view('admin.admins.form', [
            'admin'    => new Admin(),
            'roles'    => $this->assignableRoles(),
            'selected' => [],
        ] + $this->unitScopeData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'username'  => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', 'unique:admins,username'],
            'email'     => ['required', 'email', 'max:150', 'unique:admins,email'],
            'password'  => ['required', 'confirmed', AdminPasswordPolicy::rule()],
            'is_active' => ['boolean'],
            'roles'     => ['array'],
            'roles.*'   => [Rule::exists('roles', 'id')->where('guard_name', 'admin')],
            'unit_ids'   => ['nullable', 'array'],
            'unit_ids.*' => ['integer', Rule::exists('organizational_units', 'id')],
        ]);

        $this->guardSuperAdminAssignment($request, $data['roles'] ?? []);

        $admin = Admin::create([
            'name'      => $data['name'],
            'username'  => $data['username'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'is_active' => $request->boolean('is_active'),
        ]);
        $admin->syncRoles($data['roles'] ?? []);
        $this->syncUnitScope($request, $admin, $data['unit_ids'] ?? []);
        AdminAuditLog::record('admin.created', $request->user(), $admin, [
            'is_active' => $admin->is_active,
        ]);

        return redirect()->route('admin.admins.index')->with('success', 'Akun pengelola dibuat.');
    }

    public function edit(Admin $admin)
    {
        $this->guardTarget($admin);

        return view('admin.admins.form', [
            'admin'    => $admin,
            'roles'    => $this->assignableRoles(),
            'selected' => $admin->roles->pluck('id')->all(),
        ] + $this->unitScopeData($admin));
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $this->guardTarget($admin);

        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'username'  => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('admins', 'username')->ignore($admin->id)],
            'email'     => ['required', 'email', 'max:150', Rule::unique('admins', 'email')->ignore($admin->id)],
            'password'  => ['nullable', 'confirmed', AdminPasswordPolicy::rule()],
            'is_active' => ['boolean'],
            'roles'     => ['array'],
            'roles.*'   => [Rule::exists('roles', 'id')->where('guard_name', 'admin')],
            'unit_ids'   => ['nullable', 'array'],
            'unit_ids.*' => ['integer', Rule::exists('organizational_units', 'id')],
        ]);

        $roleIds  = $data['roles'] ?? [];
        $isActive = $request->boolean('is_active');
        $this->guardSuperAdminAssignment($request, $roleIds);

        // Cegah kehilangan akses: akun sendiri & super admin terakhir.
        if ($admin->is($request->user())) {
            $isActive = true;
            if ($admin->isSuperAdmin()) {
                $roleIds = array_unique([...$roleIds, $this->superAdminRoleId()]);
            }
        }
        if ($admin->isSuperAdmin()
            && ! in_array($this->superAdminRoleId(), array_map('intval', $roleIds), true)
            && $this->isLastSuperAdmin($admin)) {
            return back()->with('error', 'Tidak bisa mencabut role Super Admin dari satu-satunya Super Admin.');
        }

        $admin->fill([
            'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'], 'is_active' => $isActive,
        ]);
        if (! empty($data['password'])) {
            $admin->password = $data['password'];
        }
        $admin->save();
        $admin->syncRoles($roleIds);
        $this->syncUnitScope($request, $admin, $data['unit_ids'] ?? []);
        AdminAuditLog::record('admin.updated', $request->user(), $admin, [
            'is_active' => $admin->is_active,
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('admin.admins.index')->with('success', 'Akun pengelola diperbarui.');
    }

    public function destroy(Request $request, Admin $admin): RedirectResponse
    {
        $this->guardTarget($admin);

        if ($admin->is($request->user())) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri.');
        }
        if ($admin->isSuperAdmin() && $this->isLastSuperAdmin($admin)) {
            return back()->with('error', 'Tidak bisa menghapus satu-satunya Super Admin.');
        }

        AdminAuditLog::record('admin.deleted', $request->user(), $admin);
        $admin->roles()->detach();
        UserUnitAccess::query()->where('model_type', Admin::class)->where('model_id', $admin->id)->delete();
        $admin->delete();

        return redirect()->route('admin.admins.index')->with('success', 'Akun pengelola dihapus.');
    }

    /* ── helpers ─────────────────────────────────────────────── */

    /** Data pilihan unit untuk form akun; hanya untuk yang berhak mengatur akses unit. */
    private function unitScopeData(?Admin $admin): array
    {
        if (! request()->user()->can('organization.access.manage')) {
            return ['canSetUnits' => false, 'unitOptions' => [], 'unitSelected' => []];
        }

        $selected = $admin && $admin->exists
            ? UserUnitAccess::query()->where('model_type', Admin::class)->where('model_id', $admin->id)
                ->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return [
            'canSetUnits' => true,
            'unitOptions' => app(EmployeeAccess::class)->unitOptions(OrganizationalUnit::query()->get()),
            'unitSelected' => $selected,
        ];
    }

    /**
     * Simpan cakupan unit akun (sama seperti halaman Akses Unit). Hanya dijalankan bila field
     * unit memang dikirim dan pengelola berhak mengatur akses unit, supaya cakupan lama tidak terhapus.
     */
    private function syncUnitScope(Request $request, Admin $admin, array $unitIds): void
    {
        if (! $request->boolean('unit_scope_present') || ! $request->user()->can('organization.access.manage')) {
            return;
        }

        $unitIds = array_values(array_unique(array_map('intval', array_filter($unitIds, fn ($id) => $id !== null && $id !== ''))));

        DB::transaction(function () use ($request, $admin, $unitIds) {
            UserUnitAccess::query()->where('model_type', Admin::class)->where('model_id', $admin->id)->delete();
            foreach ($unitIds as $unitId) {
                UserUnitAccess::create([
                    'organizational_unit_id' => $unitId,
                    'model_type' => Admin::class,
                    'model_id' => $admin->id,
                    'created_at' => now(),
                ]);
            }
            AdminAuditLog::record('organization.access.updated', $request->user(), $admin, ['organizational_unit_ids' => $unitIds]);
        });
    }

    private function superAdminRoleId(): int
    {
        return (int) Role::where('guard_name', 'admin')->where('name', 'super-admin')->value('id');
    }

    private function isLastSuperAdmin(Admin $admin): bool
    {
        return Role::where('guard_name', 'admin')->where('name', 'super-admin')->first()
            ?->admins()->where('admins.id', '!=', $admin->id)->doesntExist() ?? true;
    }

    /**
     * Role organisasi inti tidak boleh diberikan oleh admin biasa. Jika tidak,
     * akun dengan izin admins.create/update dapat menaikkan dirinya/orang lain
     * menjadi Pengurus Besar atau Komisaris dan memperluas cakupan akses.
     */
    private function guardSuperAdminAssignment(Request $request, array $roleIds): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        $protectedRoles = Role::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', ['super-admin', 'pengurus-besar', 'komisaris-wilayah', 'komisaris-daerah'])
            ->whereIn('id', array_map('intval', $roleIds))
            ->exists();

        abort_if($protectedRoles, 403, 'Hanya Super Admin yang dapat menetapkan role inti organisasi.');
    }

    /** Non-super-admin tidak boleh mengubah/menghapus akun Super Admin. */
    private function guardTarget(Admin $admin): void
    {
        abort_if($admin->isSuperAdmin() && ! request()->user()->isSuperAdmin(), 403);
    }

    private function assignableRoles()
    {
        return Role::where('guard_name', 'admin')
            ->when(! request()->user()->isSuperAdmin(), fn ($q) => $q->whereNotIn('name', [
                'super-admin', 'pengurus-besar', 'komisaris-wilayah', 'komisaris-daerah',
            ]))
            ->orderBy('id')->get();
    }
}
