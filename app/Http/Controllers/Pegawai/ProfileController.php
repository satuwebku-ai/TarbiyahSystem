<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Auth\BaseProfileController;

class ProfileController extends BaseProfileController
{
    protected function guardName(): string { return 'pegawai'; }
    protected function profileView(): string { return 'pegawai.profile'; }
}
