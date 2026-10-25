<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\BaseTwoFactorController;

/** 2FA akun pengelola sendiri (halaman Profil Saya). Pengaturan kebijakan ada di TwoFactorSettingsController. */
class TwoFactorController extends BaseTwoFactorController
{
    protected function guardName(): string { return 'admin'; }
}
