<?php

return [

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'pelajar'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'student_accounts'),
    ],

    /*
    | Tiga jenis akun, tiga guard, tiga tabel:
    |   admin   -> admins            Pengurus Besar, KOMWIL, KOMDA       pengelola.<domain>
    |   pegawai -> users             Pegawai dan guru                    pegawai.<domain>
    |   pelajar -> student_accounts  Pelajar (login NIS)                 pelajar.<domain>
    */
    'guards' => [
        'admin'   => ['driver' => 'session', 'provider' => 'admins'],
        'pegawai' => ['driver' => 'session', 'provider' => 'users'],
        'pelajar' => ['driver' => 'session', 'provider' => 'student_accounts'],
    ],

    'providers' => [
        'admins'           => ['driver' => 'eloquent', 'model' => App\Models\Admin::class],
        'users'            => ['driver' => 'eloquent', 'model' => App\Models\User::class],
        'student_accounts' => ['driver' => 'eloquent', 'model' => App\Models\StudentAccount::class],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table'    => 'admin_password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
        'student_accounts' => [
            'provider' => 'student_accounts',
            'table'    => 'student_account_password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
