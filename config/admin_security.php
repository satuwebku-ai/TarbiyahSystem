<?php

return [
    'defaults' => [
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
        'session_idle_minutes' => 30,
        'password_min_length' => 12,
        'password_require_mixed_case' => true,
        'password_require_numbers' => true,
        'password_require_symbols' => false,
    ],

    // Batas percobaan login GAGAL per alamat IP (semua username digabung), per guard.
    // Melengkapi batas per username+IP: mencegah password spraying dengan mengganti-ganti username.
    // Jangan terlalu kecil: satu IP sekolah (NAT) bisa dipakai banyak siswa sekaligus.
    'login_ip_max_failures' => (int) env('LOGIN_IP_MAX_FAILURES', 30),
    'login_ip_decay_seconds' => (int) env('LOGIN_IP_DECAY_SECONDS', 600),
];
