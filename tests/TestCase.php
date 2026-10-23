<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ganti akun di tengah tes = login baru. Sesi dikosongkan dulu supaya hash password akun
     * sebelumnya (disimpan middleware AuthenticateSession) tidak membuat akun baru terlogout.
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }
}
