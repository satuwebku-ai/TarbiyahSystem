<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use App\Rules\AdminPasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FirstAdminSetupController extends Controller
{
    public function show(): View
    {
        abort_unless(app()->environment(['local', 'development', 'testing']), 404);
        abort_if(Admin::query()->exists(), 404);

        return view('admin.auth.setup');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'development', 'testing']), 404);
        abort_if(Admin::query()->exists(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:admins,username'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', AdminPasswordPolicy::rule()],
        ]);

        $admin = DB::transaction(function () use ($data) {
            abort_if(Admin::query()->lockForUpdate()->exists(), 404);

            $admin = Admin::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $superAdmin = Role::query()->firstOrCreate(
                ['name' => 'super-admin', 'guard_name' => 'admin'],
                ['label' => 'Super Admin', 'description' => 'Akses penuh ke seluruh fitur', 'is_system' => true],
            );
            $admin->syncRoles([$superAdmin->id]);

            return $admin;
        });

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('success', 'Akun Super Admin berhasil dibuat.');
    }
}
