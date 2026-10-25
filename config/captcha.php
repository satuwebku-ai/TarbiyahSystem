<?php

/*
| CAPTCHA untuk halaman login, lupa kata sandi, dan pendaftaran pelajar.
| Nilai di bawah adalah bawaan dari .env; bisa ditimpa dari menu Pengaturan Aplikasi > CAPTCHA.
|
| provider : off | math | turnstile | recaptcha | hcaptcha
|   math       pertanyaan hitung sederhana buatan sendiri (tanpa akun eksternal; hanya menahan bot paling dasar)
|   turnstile  Cloudflare Turnstile (gratis, tanpa teka-teki gambar)
|   recaptcha  Google reCAPTCHA v2 ("Saya bukan robot")
|   hcaptcha   hCaptcha
| Provider eksternal butuh site key (publik) dan secret key (rahasia) dari dashboard layanan tersebut.
| Bila provider eksternal dipilih tetapi kuncinya kosong, CAPTCHA tidak diterapkan (agar tidak mengunci semua orang).
*/
return [
    'provider' => env('CAPTCHA_PROVIDER', 'off'),
    'site_key' => env('CAPTCHA_SITE_KEY'),
    'secret_key' => env('CAPTCHA_SECRET_KEY'),

    'pages' => [
        'login' => filter_var(env('CAPTCHA_LOGIN', true), FILTER_VALIDATE_BOOLEAN),
        'forgot' => filter_var(env('CAPTCHA_FORGOT', true), FILTER_VALIDATE_BOOLEAN),
        'register' => filter_var(env('CAPTCHA_REGISTER', true), FILTER_VALIDATE_BOOLEAN),
    ],

    // Detik menunggu jawaban layanan verifikasi sebelum dianggap gagal.
    'timeout' => 5,

    // Umur pertanyaan "math" (menit) dan jumlah maksimum yang disimpan di sesi.
    'math_ttl_minutes' => 10,

    'providers' => [
        'turnstile' => [
            'label' => 'Cloudflare Turnstile',
            'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'field' => 'cf-turnstile-response',
            'widget_class' => 'cf-turnstile',
            // Host tambahan yang harus diizinkan CSP saat provider ini aktif.
            'csp' => [
                'script' => ['https://challenges.cloudflare.com'],
                'frame' => ['https://challenges.cloudflare.com'],
                'connect' => ['https://challenges.cloudflare.com'],
            ],
        ],
        'recaptcha' => [
            'label' => 'Google reCAPTCHA v2',
            'script' => 'https://www.google.com/recaptcha/api.js',
            'verify' => 'https://www.google.com/recaptcha/api/siteverify',
            'field' => 'g-recaptcha-response',
            'widget_class' => 'g-recaptcha',
            'csp' => [
                'script' => ['https://www.google.com', 'https://www.gstatic.com'],
                'frame' => ['https://www.google.com'],
                'connect' => [],
            ],
        ],
        'hcaptcha' => [
            'label' => 'hCaptcha',
            'script' => 'https://js.hcaptcha.com/1/api.js',
            'verify' => 'https://api.hcaptcha.com/siteverify',
            'field' => 'h-captcha-response',
            'widget_class' => 'h-captcha',
            'csp' => [
                'script' => ['https://js.hcaptcha.com', 'https://*.hcaptcha.com'],
                'frame' => ['https://*.hcaptcha.com'],
                'connect' => ['https://*.hcaptcha.com'],
                'style' => ['https://*.hcaptcha.com'],
            ],
        ],
    ],
];
