<?php

namespace App\Http\Controllers\Pelajar;

use App\Http\Controllers\Auth\BasePasswordResetController;

class PasswordResetController extends BasePasswordResetController
{
    protected function broker(): string { return 'student_accounts'; }
    protected function viewPrefix(): string { return 'pelajar.auth'; }
    protected function loginRoute(): string { return 'pelajar.login'; }
}
