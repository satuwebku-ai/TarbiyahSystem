<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Rules\AdminPasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

abstract class BaseProfileController extends Controller
{
    abstract protected function guardName(): string;
    abstract protected function profileView(): string;

    /** Aturan validasi profil; subclass boleh menimpa (mis. email opsional). */
    protected function profileRules($user): array
    {
        return [
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique($user->getTable(), 'email')->ignore($user->id)],
        ];
    }

    public function edit(Request $request): View
    {
        return view($this->profileView(), ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->update($request->validate($this->profileRules($user)));

        return back()->with('success', 'Profil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $passwordRule = in_array($this->guardName(), ['admin', 'pegawai'], true)
            ? AdminPasswordPolicy::rule()
            : PasswordRule::min(8)->letters()->numbers();

        $data = $request->validate([
            'current_password' => ['required', 'current_password:' . $this->guardName()],
            'password'         => ['required', 'confirmed', 'different:current_password', $passwordRule],
        ]);

        $request->user()->update(['password' => $data['password']]);
        if (in_array($this->guardName(), ['admin', 'pegawai'], true)) {
            AdminAuditLog::record('admin.password_changed', $request->user(), $request->user(), ['via' => $this->guardName()]);
        }

        return back()->with('success', 'Kata sandi diubah.');
    }
}
