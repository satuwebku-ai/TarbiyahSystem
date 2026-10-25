<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Rules\AdminPasswordPolicy;
use App\Services\Captcha;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

abstract class BasePasswordResetController extends Controller
{
    abstract protected function broker(): string;       // admins | users | student_accounts
    abstract protected function viewPrefix(): string;   // mis. pelajar.auth
    abstract protected function loginRoute(): string;

    public function showForgot(): View
    {
        return view($this->viewPrefix() . '.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        app(Captcha::class)->verify($request, 'forgot');

        $status = Password::broker($this->broker())->sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => 'Terlalu sering meminta. Coba lagi sebentar lagi.']);
        }

        // Pesan sama untuk email terdaftar/tidak (mencegah enumerasi akun).
        return back()->with('status', 'Jika email terdaftar, tautan reset kata sandi telah dikirim.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view($this->viewPrefix() . '.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $passwordRule = in_array($this->broker(), ['admins', 'users'], true)
            ? AdminPasswordPolicy::rule()
            : PasswordRule::min(8)->letters()->numbers();

        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', $passwordRule],
        ]);

        $status = Password::broker($this->broker())->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                if (in_array($this->broker(), ['admins', 'users'], true)) {
                    AdminAuditLog::record('admin.password_reset', $user, $user);
                }
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route($this->loginRoute())->with('status', 'Kata sandi berhasil diubah. Silakan masuk.')
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa.']);
    }
}
