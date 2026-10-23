<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Auth\BaseAuthController;
use App\Services\PegawaiPortalAccess;
use Illuminate\Support\Str;

/** Login portal Pegawai/Guru: username akun (tabel users) yang sudah dihubungkan ke data pegawai. */
class AuthController extends BaseAuthController
{
    public function __construct(private PegawaiPortalAccess $access) {}

    protected function guardName(): string { return 'pegawai'; }
    protected function loginView(): string { return 'pegawai.auth.login'; }
    protected function homeRoute(): string { return 'pegawai.dashboard'; }
    protected function loginRoute(): string { return 'pegawai.login'; }

    protected function loginField(): string { return 'username'; }

    protected function loginRules(): array
    {
        return ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'];
    }

    protected function credentialColumn(string $identifier): array
    {
        return ['username' => Str::lower($identifier)];
    }

    protected function failedMessage(): string
    {
        return 'Username atau kata sandi salah, atau akun dinonaktifkan.';
    }

    protected function denyLogin($user): ?string
    {
        return $this->access->denialReason($user);
    }
}
