<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use App\Models\StudentAccount;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'totalAdmins'  => Admin::count(),
            'activeAdmins' => Admin::where('is_active', true)->count(),
            'totalRoles'   => Role::where('guard_name', 'admin')->count(),
            'totalPelajar' => StudentAccount::count(),
            'recentAdmins' => Admin::with('roles')->orderByDesc('last_login_at')->limit(5)->get(),
            'newPelajar'   => StudentAccount::latest()->limit(5)->get(),
        ]);
    }
}
