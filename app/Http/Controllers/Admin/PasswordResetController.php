<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\BasePasswordResetController;

class PasswordResetController extends BasePasswordResetController
{
    protected function broker(): string { return 'admins'; }
    protected function viewPrefix(): string { return 'admin.auth'; }
    protected function loginRoute(): string { return 'admin.login'; }
}
