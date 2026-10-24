<?php

return [
    // Zona waktu bisnis absensi. Waktu disimpan UTC; tanggal & jam kerja dihitung di zona ini.
    'timezone' => env('ATTENDANCE_TIMEZONE', 'Asia/Makassar'),

    // Zona waktu yang boleh dipilih pada kebijakan absensi (kunci = nama zona IANA).
    'timezones' => [
        'Asia/Jakarta' => ['WIB', 'WIB (UTC+7)'],
        'Asia/Makassar' => ['WITA', 'WITA (UTC+8)'],
        'Asia/Jayapura' => ['WIT', 'WIT (UTC+9)'],
    ],

    // Batas hari ke belakang untuk mengajukan koreksi absensi.
    'correction_window_days' => 14,

    // Akurasi GPS terburuk (meter) yang masih diterima saat absen dengan syarat lokasi.
    'max_gps_accuracy_m' => (int) env('ATTENDANCE_MAX_GPS_ACCURACY', 150),

    // Rentang maksimum (hari kalender) untuk pencatatan izin/sakit/cuti sekali input.
    'max_entry_range_days' => 62,
];
