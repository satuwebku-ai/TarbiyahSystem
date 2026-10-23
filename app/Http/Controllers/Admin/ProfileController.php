<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Auth\BaseProfileController;

class ProfileController extends BaseProfileController
{
    protected function guardName(): string { return 'admin'; }
    protected function profileView(): string { return 'admin.profile'; }
}
