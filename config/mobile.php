<?php

return [
    // Masa berlaku token mobile (hari) sejak login. Setelah itu pengguna harus masuk lagi.
    'token_ttl_days' => (int) env('MOBILE_TOKEN_TTL_DAYS', 30),

    // Perpanjang masa berlaku token otomatis selama aplikasi masih dipakai (geser sejak pemakaian terakhir).
    'sliding_expiry' => (bool) env('MOBILE_SLIDING_EXPIRY', true),

    // Batas mutlak umur sesi (hari sejak login) walau terus dipakai; setelah itu wajib masuk ulang.
    'token_max_days' => (int) env('MOBILE_TOKEN_MAX_DAYS', 180),

    // Jumlah perangkat aktif per akun. Login baru melewati batas ini mencabut token yang paling lama.
    'max_tokens_per_account' => (int) env('MOBILE_MAX_TOKENS', 5),
];
