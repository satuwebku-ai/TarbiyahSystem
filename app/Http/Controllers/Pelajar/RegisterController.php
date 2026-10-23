<?php

namespace App\Http\Controllers\Pelajar;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('pelajar.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'nis'      => ['required', 'string', 'regex:/^[0-9A-Za-z.\-]+$/', 'max:30', 'unique:student_accounts,nis'],
            'email'    => ['nullable', 'email', 'max:150', 'unique:student_accounts,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        // NIS harus milik pelajar aktif di data sekolah yang belum punya akun; akun dikaitkan ke data itu.
        $user = DB::transaction(function () use ($data) {
            $student = Student::query()
                ->where('nis', $data['nis'])
                ->where('status', 'active')
                ->whereNull('student_account_id')
                ->lockForUpdate()
                ->first();

            if (! $student) {
                throw ValidationException::withMessages([
                    'nis' => 'NIS tidak ditemukan pada data pelajar aktif atau sudah memiliki akun. Hubungi admin sekolah.',
                ]);
            }

            $account = StudentAccount::create([
                'name'      => $student->name,
                'nis'       => $data['nis'],
                'email'     => $data['email'] ?? null,
                'password'  => $data['password'],
                'is_active' => true,
            ]);
            $student->forceFill(['student_account_id' => $account->id])->save();

            return $account;
        });

        $role = Role::where('guard_name', 'pelajar')->where('name', 'pelajar')->first();
        if ($role) {
            $user->syncRoles([$role->id]);
        }

        Auth::guard('pelajar')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return redirect()->route('pelajar.dashboard');
    }
}
