<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use App\Models\User;
use App\Rules\AdminPasswordPolicy;
use App\Services\EmployeeAccess;
use App\Services\PegawaiPortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Akun login portal pegawai (tabel users): dibuat, dihubungkan, atau dilepas dari data pegawai. */
class EmployeeAccountController extends Controller
{
    /** Hubungkan akun users yang sudah ada, atau lepas akun (admin_id kosong = lepas). */
    public function update(Request $request, Employee $employee, EmployeeAccess $access): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);

        $data = $request->validate([
            'user_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', true),
                Rule::unique('employees', 'user_id')->ignore($employee->id),
            ],
        ], ['user_id.unique' => 'Akun ini sudah terhubung ke pegawai lain.']);

        $previous = $employee->user_id;
        $employee->update(['user_id' => $data['user_id'] ?? null]);
        AdminAuditLog::record('employee.account_linked', $admin, $employee, ['from_user_id' => $previous, 'to_user_id' => $employee->user_id]);

        return back()->with('success', $employee->user_id ? 'Akun berhasil dihubungkan.' : 'Akun dilepas dari data pegawai.');
    }

    /** Buat akun portal baru dan langsung hubungkan ke pegawai ini. */
    public function store(Request $request, Employee $employee, EmployeeAccess $access, PegawaiPortalAccess $portal): RedirectResponse
    {
        $admin = $request->user('admin');
        $access->assertCanManage($admin, $employee);

        abort_if($employee->user_id, 422, 'Pegawai ini sudah punya akun portal.');

        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', AdminPasswordPolicy::rule()],
        ]);

        $user = DB::transaction(function () use ($employee, $data, $portal) {
            $user = User::create([
                'name' => $employee->name,
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->syncRoles([$portal->defaultRole()->id]);
            $employee->update(['user_id' => $user->id]);

            return $user;
        });
        AdminAuditLog::record('employee.account_created', $admin, $employee, ['user_id' => $user->id, 'username' => $user->username]);

        return back()->with('success', 'Akun portal pegawai dibuat dan dihubungkan.');
    }
}
