<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Auth\BasePasswordResetController;

class PasswordResetController extends BasePasswordResetController
{
    protected function broker(): string { return 'users'; }
    protected function viewPrefix(): string { return 'pegawai.auth'; }
    protected function loginRoute(): string { return 'pegawai.login'; }
}
