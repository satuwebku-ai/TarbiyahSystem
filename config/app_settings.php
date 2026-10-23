<?php

/*
| Definisi menu Pengaturan Aplikasi (sumber kebenaran: kunci apa saja yang boleh diubah dari UI).
| Nilai tersimpan di tabel app_settings dan MENIMPA nilai .env saat aplikasi boot (lihat App\Services\AppSettings).
| Kunci yang tidak ada di sini tidak bisa diubah lewat UI (APP_KEY, DB_*, APP_DEBUG, domain, TRUSTED_PROXIES, dst. tetap di .env).
|
| Field : label, type (text|email|number|bool|select|secret), config (daftar kunci config() yang diisi),
|         rules (validasi Laravel), options (untuk select), help (opsional), nullable (kosong => null).
| Tipe secret : disimpan terenkripsi, tidak pernah ditampilkan kembali; kosong saat disimpan = jangan ubah.
*/
return [
    'groups' => [

        'umum' => [
            'label' => 'Umum',
            'description' => 'Identitas aplikasi dan zona waktu absensi.',
            'fields' => [
                'app_name' => [
                    'label' => 'Nama aplikasi', 'type' => 'text', 'config' => ['app.name'],
                    'rules' => ['required', 'string', 'max:100'],
                ],
                'attendance_timezone' => [
                    'label' => 'Zona waktu absensi', 'type' => 'select', 'config' => ['attendance.timezone'],
                    'options' => ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'],
                    'rules' => ['required', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura'],
                    'help' => 'Samakan dengan lokasi sekolah dan jam pada mesin fingerprint.',
                ],
            ],
        ],

        'email' => [
            'label' => 'Email',
            'description' => 'Pengiriman email notifikasi. Email dikirim lewat antrean, jadi worker antrean harus hidup.',
            'fields' => [
                'mail_mailer' => [
                    'label' => 'Metode pengiriman', 'type' => 'select', 'config' => ['mail.default'],
                    'options' => ['smtp' => 'SMTP (kirim sungguhan)', 'log' => 'Log saja (tidak terkirim)'],
                    'rules' => ['required', 'in:smtp,log'],
                ],
                'mail_host' => [
                    'label' => 'Host SMTP', 'type' => 'text', 'config' => ['mail.mailers.smtp.host'], 'nullable' => true,
                    'rules' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:255'],
                ],
                'mail_port' => [
                    'label' => 'Port SMTP', 'type' => 'number', 'config' => ['mail.mailers.smtp.port'], 'nullable' => true,
                    'rules' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'between:1,65535'],
                ],
                'mail_scheme' => [
                    'label' => 'Enkripsi', 'type' => 'select', 'config' => ['mail.mailers.smtp.scheme'], 'nullable' => true,
                    'options' => ['' => 'Otomatis (berdasarkan port)', 'smtp' => 'smtp (STARTTLS)', 'smtps' => 'smtps (SSL/TLS)'],
                    'rules' => ['nullable', 'in:smtp,smtps'],
                ],
                'mail_username' => [
                    'label' => 'Username SMTP', 'type' => 'text', 'config' => ['mail.mailers.smtp.username'], 'nullable' => true,
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                'mail_password' => [
                    'label' => 'Password SMTP', 'type' => 'secret', 'config' => ['mail.mailers.smtp.password'],
                    'rules' => ['nullable', 'string', 'max:255'],
                    'help' => 'Disimpan terenkripsi. Kosongkan bila tidak ingin mengubah.',
                ],
                'mail_from_address' => [
                    'label' => 'Alamat pengirim', 'type' => 'email', 'config' => ['mail.from.address'],
                    'rules' => ['required', 'email', 'max:255'],
                ],
                'mail_from_name' => [
                    'label' => 'Nama pengirim', 'type' => 'text', 'config' => ['mail.from.name'],
                    'rules' => ['required', 'string', 'max:100'],
                ],
                'notify_mail' => [
                    'label' => 'Kirim notifikasi lewat email', 'type' => 'bool', 'config' => ['notifications.mail'],
                    'rules' => ['boolean'],
                    'help' => 'Bila mati, notifikasi lonceng di aplikasi tetap berjalan.',
                ],
            ],
        ],

        'pendaftaran' => [
            'label' => 'Pendaftaran Pelajar',
            'description' => 'Pendaftaran mandiri akun pelajar di portal pelajar.',
            'fields' => [
                'pelajar_registration' => [
                    'label' => 'Buka pendaftaran mandiri pelajar', 'type' => 'bool', 'config' => ['domains.pelajar_registration'],
                    'rules' => ['boolean'],
                    'help' => 'Bila mati, halaman pendaftaran tidak dapat dibuka dan tautannya disembunyikan.',
                ],
            ],
        ],

        'fingerprint' => [
            'label' => 'Fingerprint',
            'description' => 'Batas pengaman untuk data ketukan yang dikirim mesin (/iclock).',
            'fields' => [
                'fingerprint_max_future_minutes' => [
                    'label' => 'Toleransi ketukan di masa depan (menit)', 'type' => 'number', 'config' => ['student_attendance.max_future_minutes'],
                    'rules' => ['required', 'integer', 'between:0,1440'],
                    'help' => 'Ketukan yang waktunya melebihi batas ini dari sekarang ditolak.',
                ],
                'fingerprint_max_punch_age_days' => [
                    'label' => 'Usia maksimum ketukan (hari)', 'type' => 'number', 'config' => ['student_attendance.max_punch_age_days'],
                    'rules' => ['required', 'integer', 'between:1,3650'],
                    'help' => 'Ketukan yang lebih lama dari batas ini ditolak.',
                ],
                'fingerprint_max_punches_per_request' => [
                    'label' => 'Maksimum ketukan per permintaan', 'type' => 'number', 'config' => ['student_attendance.max_punches_per_request'],
                    'rules' => ['required', 'integer', 'between:1,10000'],
                ],
                'fingerprint_max_body_bytes' => [
                    'label' => 'Ukuran maksimum permintaan (byte)', 'type' => 'number', 'config' => ['student_attendance.max_body_bytes'],
                    'rules' => ['required', 'integer', 'between:1024,5242880'],
                ],
            ],
        ],

        'keamanan' => [
            'label' => 'Keamanan',
            'description' => 'Pembatasan percobaan login gagal per alamat IP (semua username digabung) di portal pengelola, pegawai, dan pelajar.',
            'fields' => [
                'login_ip_max_failures' => [
                    'label' => 'Maksimum login gagal per IP', 'type' => 'number', 'config' => ['admin_security.login_ip_max_failures'],
                    'rules' => ['required', 'integer', 'between:3,1000'],
                    'help' => 'Jangan terlalu kecil: satu IP sekolah (NAT) dipakai banyak pengguna sekaligus, sehingga satu kelas yang salah ketik password bisa memblokir semuanya.',
                ],
                'login_ip_decay_seconds' => [
                    'label' => 'Lama blokir / jendela hitung (detik)', 'type' => 'number', 'config' => ['admin_security.login_ip_decay_seconds'],
                    'rules' => ['required', 'integer', 'between:60,86400'],
                    'help' => 'Hitungan gagal per IP direset setelah selang waktu ini. Minimum 60 detik.',
                ],
            ],
        ],
    ],
];
