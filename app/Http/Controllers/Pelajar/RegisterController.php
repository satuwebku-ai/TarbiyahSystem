<?php

namespace App\Http\Controllers\Pelajar;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAccount;
use App\Services\Captcha;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
        $mismatch = 'Data tidak cocok dengan pelajar aktif yang belum memiliki akun. Periksa NIS dan tanggal lahir, atau hubungi admin sekolah.';

        $data = $request->validate([
            'nis'        => ['required', 'string', 'regex:/^[0-9A-Za-z.\-]+$/', 'max:30', 'unique:student_accounts,nis'],
            'birth_date' => ['required', 'date_format:Y-m-d'],
            'email'      => ['nullable', 'email', 'max:150', 'unique:student_accounts,email'],
            'password'   => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], ['nis.unique' => $mismatch]);
        app(Captcha::class)->verify($request, 'register');

        // Tebakan tanggal lahir untuk satu NIS dibatasi, selain batas per IP di rute.
        $limitKey = 'pelajar-register:'.sha1(strtolower($data['nis']));
        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            throw ValidationException::withMessages(['nis' => 'Terlalu banyak percobaan untuk NIS ini. Coba lagi nanti atau hubungi admin sekolah.']);
        }

        // NIS + tanggal lahir harus cocok dengan pelajar aktif di data sekolah yang belum punya akun;
        // pesan galat sama untuk semua penyebab agar NIS tidak bisa ditebak keberadaannya.
        $user = DB::transaction(function () use ($data, $limitKey, $mismatch) {
            $student = Student::query()
                ->where('nis', $data['nis'])
                ->where('status', 'active')
                ->whereNull('student_account_id')
                ->lockForUpdate()
                ->first();

            if (! $student || $student->birth_date?->toDateString() !== $data['birth_date']) {
                RateLimiter::hit($limitKey, 3600);

                throw ValidationException::withMessages(['nis' => $mismatch]);
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
        RateLimiter::clear($limitKey);

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
