<?php

$root = env('APP_DOMAIN', 'localhost');

return [
    'routing_mode' => env('AUTH_ROUTING_MODE', 'path'),
    'root'   => $root,
    'admin'  => env('ADMIN_SUBDOMAIN', 'pengelola') . '.' . $root,
    'pelajar' => env('PELAJAR_SUBDOMAIN', 'pelajar') . '.' . $root,
    'pegawai' => env('PEGAWAI_SUBDOMAIN', 'pegawai') . '.' . $root,   // portal Pegawai/Guru

    // Izinkan pelajar mendaftar sendiri di pelajar.<domain>/register
    'pelajar_registration' => (bool) env('PELAJAR_REGISTRATION', false),

    // Data contoh fiktif (unit, jabatan, sekolah, pegawai, penugasan, siswa) lewat db:seed.
    // Bawaan: hanya di luar production. Isi SEED_SAMPLE_DATA=true untuk server uji coba berstatus production.
    'seed_sample_data' => filter_var(env('SEED_SAMPLE_DATA', false), FILTER_VALIDATE_BOOLEAN),

    // Akun Super Admin awal (dipakai SuperAdminSeeder)
    'seed_admin' => [
        'name'     => env('ADMIN_NAME', 'Super Admin'),
        'username' => env('ADMIN_USERNAME', 'admin'),
        'email'    => env('ADMIN_EMAIL', 'admin@namadomain.com'),
        'password' => env('ADMIN_PASSWORD'),   // kosong => non-production: password awal bawaan (lihat SuperAdminSeeder), production: acak
    ],
];
