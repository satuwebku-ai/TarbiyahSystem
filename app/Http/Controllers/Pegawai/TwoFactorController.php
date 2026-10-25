<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Auth\BaseTwoFactorController;

/** 2FA akun pegawai sendiri (halaman Profil & Keamanan). */
class TwoFactorController extends BaseTwoFactorController
{
    protected function guardName(): string { return 'pegawai'; }
}
