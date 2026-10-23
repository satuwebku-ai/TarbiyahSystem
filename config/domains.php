<?php

$root = env('APP_DOMAIN', 'localhost');

return [
    'routing_mode' => env('AUTH_ROUTING_MODE', 'path'),
    'root'   => $root,
    'admin'  => env('ADMIN_SUBDOMAIN', 'pengelola') . '.' . $root,
    'pelajar' => env('PELAJAR_SUBDOMAIN', 'pelajar') . '.' . $root,
    'pegawai' => env('PEGAWAI_SUBDOMAIN', 'pegawai') . '.' . $root,   // portal Pegawai/Guru

    // Izinkan pelajar mendaftar sendiri di pelajar.<domain>/register
    'pelajar_registration' => (bool) env('PELAJAR_REGISTRATION', true),

    // Tombol "Akun Demo" (login satu klik) di halaman login. Bawaan: hanya aktif di APP_ENV=local.
    // Atur DEMO_LOGIN=true/false di .env. Selalu nonaktif di produksi kecuali DEMO_LOGIN=true diisi eksplisit.
    'demo_login' => filter_var(env('DEMO_LOGIN', env('APP_ENV') === 'local'), FILTER_VALIDATE_BOOLEAN),

    // Akun Super Admin awal (dipakai SuperAdminSeeder)
    'seed_admin' => [
        'name'     => env('ADMIN_NAME', 'Super Admin'),
        'username' => env('ADMIN_USERNAME', 'admin'),
        'email'    => env('ADMIN_EMAIL', 'admin@namadomain.com'),
        'password' => env('ADMIN_PASSWORD'),   // kosong => non-production: password awal bawaan (lihat SuperAdminSeeder), production: acak
    ],
];
