<?php

/*
| Nilai bawaan pengaturan absensi siswa. Dipakai bila belum ada pengaturan tersimpan
| (menu Pengaturan Absensi Siswa: umum atau khusus per sekolah).
*/
return [
    'defaults' => [
        'fingerprint_enabled' => true,
        'session_name' => 'Harian',
        'school_days' => [1, 2, 3, 4, 5, 6],   // 1 = Senin ... 7 = Minggu
        'start_time' => '07:00',
        'late_tolerance_minutes' => 10,
        'checkin_open_time' => '05:00',
        'checkin_close_time' => '12:00',
        'skip_holidays' => true,
        'auto_absent_enabled' => false,
        'auto_absent_time' => '10:00',
    ],

    // Mesin aktif WAJIB punya daftar IP/CIDR (allowed_ips) agar log diterima. Tanpa ini siapa pun yang
    // tahu nomor seri mesin (tertera di badan mesin) bisa menyuntik log absensi palsu lewat /iclock.
    // Bawaan: aktif di production, mati di lingkungan lain. Atur FINGERPRINT_REQUIRE_ALLOWED_IPS=false
    // hanya bila IP mesin tidak bisa dibatasi (dan terima risikonya).
    'require_allowed_ips' => filter_var(
        env('FINGERPRINT_REQUIRE_ALLOWED_IPS', env('APP_ENV') === 'production'),
        FILTER_VALIDATE_BOOLEAN,
    ),

    // Perangkat baru yang belum diklaim dibatasi agar endpoint tidak bisa dipakai membanjiri tabel.
    'max_pending_devices' => 20,

    // Batas keamanan endpoint push mesin (/iclock/cdata).
    // Ketukan lebih baru dari sekarang + toleransi, atau lebih lama dari batas usia, tidak diproses
    // (disimpan dengan hasil "bad_time" agar admin bisa melihat jam mesin yang salah).
    'max_future_minutes' => (int) env('FINGERPRINT_MAX_FUTURE_MINUTES', 10),
    'max_punch_age_days' => (int) env('FINGERPRINT_MAX_PUNCH_AGE_DAYS', 60),

    // Satu request ditolak (413) bila isinya melebihi batas ini; mesin akan mencoba lagi nanti.
    'max_punches_per_request' => (int) env('FINGERPRINT_MAX_PUNCHES_PER_REQUEST', 1000),
    'max_body_bytes' => (int) env('FINGERPRINT_MAX_BODY_BYTES', 262144),
];
