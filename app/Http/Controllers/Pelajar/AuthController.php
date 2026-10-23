<?php

namespace App\Http\Controllers\Pelajar;

use App\Http\Controllers\Auth\BaseAuthController;

/** Login pelajar memakai NIS (Nomor Induk Pelajar), bukan email. */
class AuthController extends BaseAuthController
{
    protected function guardName(): string { return 'pelajar'; }
    protected function loginView(): string { return 'pelajar.auth.login'; }
    protected function homeRoute(): string { return 'pelajar.dashboard'; }
    protected function loginRoute(): string { return 'pelajar.login'; }

    protected function loginField(): string { return 'nis'; }
    protected function loginRules(): array { return ['required', 'string', 'max:30']; }
    protected function credentialColumn(string $identifier): array { return ['nis' => $identifier]; }

    protected function failedMessage(): string
    {
        return 'NIS atau kata sandi salah, atau akun dinonaktifkan.';
    }
}
