<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\BaseAuthController;

class AuthController extends BaseAuthController
{
    protected function guardName(): string { return 'admin'; }
    protected function loginView(): string { return 'admin.auth.login'; }
    protected function homeRoute(): string { return 'admin.dashboard'; }
    protected function loginRoute(): string { return 'admin.login'; }

    // Admin login hanya dengan username.
    protected function loginField(): string { return 'username'; }

    protected function loginRules(): array
    {
        return ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'];
    }

    protected function credentialColumn(string $identifier): array
    {
        return ['username' => \Illuminate\Support\Str::lower($identifier)];
    }

    protected function failedMessage(): string
    {
        return 'Username atau kata sandi salah, atau akun dinonaktifkan.';
    }
}
