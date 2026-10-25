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

    // Masa simpan foto selfie absen (hari). Lewat batas ini berkas foto dihapus oleh
    // `attendance:prune-photos`; data absen tetap. Isi 0 untuk menyimpan tanpa batas.
    'photo_retention_days' => (int) env('ATTENDANCE_PHOTO_RETENTION_DAYS', 90),

    // Pemeriksaan foto selfie absen. Semuanya dapat diubah dari menu Pengaturan Aplikasi > Absensi.
    // Tolak foto yang berkasnya identik dengan foto absen sebelumnya.
    'photo_reject_duplicate' => (bool) env('ATTENDANCE_PHOTO_REJECT_DUPLICATE', true),
    // Tandai foto berciri galeri (EXIF, lebar > 640 px, bukan JPEG) agar admin memeriksanya.
    'photo_flag_suspicious' => (bool) env('ATTENDANCE_PHOTO_FLAG_SUSPICIOUS', true),
    // Bubuhkan nama, jenis absen, dan waktu server pada foto yang disimpan.
    'photo_watermark' => (bool) env('ATTENDANCE_PHOTO_WATERMARK', true),

    // Rentang maksimum (hari kalender) untuk pencatatan izin/sakit/cuti sekali input.
    'max_entry_range_days' => 62,
];
