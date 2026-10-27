<?php

return [
    // Masa berlaku token mobile (hari) sejak login. Setelah itu pengguna harus masuk lagi.
    'token_ttl_days' => (int) env('MOBILE_TOKEN_TTL_DAYS', 30),

    // Jumlah perangkat aktif per akun. Login baru melewati batas ini mencabut token yang paling lama.
    'max_tokens_per_account' => (int) env('MOBILE_MAX_TOKENS', 5),
];
