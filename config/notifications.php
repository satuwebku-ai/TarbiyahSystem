<?php

/*
| Pengaturan notifikasi & pengingat otomatis (Fase 10).
| Notifikasi dalam aplikasi (lonceng) selalu aktif. Email dapat dimatikan lewat NOTIFY_MAIL=false.
*/
return [
    // Kirim juga lewat email (hanya ke akun yang punya alamat email).
    'mail' => env('NOTIFY_MAIL', true),

    // Pengajuan yang belum diproses setelah sekian hari akan diingatkan ke penyetuju.
    'pending_reminder_after_days' => (int) env('NOTIFY_PENDING_AFTER_DAYS', 3),

    // Pengingat kegiatan dikirim sekian hari sebelum kegiatan dimulai.
    'activity_reminder_days_ahead' => (int) env('NOTIFY_ACTIVITY_DAYS_AHEAD', 1),

    // Notifikasi push ke aplikasi mobile (lewat layanan push Expo). Hanya terkirim ke perangkat yang sudah mendaftarkan token.
    'push' => env('NOTIFY_PUSH', true),
    // Opsional: Access Token Expo bila "Enhanced Security for Push Notifications" diaktifkan di akun Expo.
    'expo_access_token' => env('EXPO_ACCESS_TOKEN'),

    // Pengingat jam absen lewat push (hanya untuk pegawai yang memasang aplikasi mobile).
    // Pengingat masuk dikirim sekian menit sebelum jam masuk; keduanya tetap dikirim bila terlambat sampai batas jendela di bawah.
    'checkin_reminder_lead_minutes' => (int) env('NOTIFY_CHECKIN_LEAD_MINUTES', 15),
    'reminder_window_minutes' => (int) env('NOTIFY_REMINDER_WINDOW_MINUTES', 60),

    // Notifikasi yang sudah dibaca dihapus setelah sekian hari.
    'retention_days' => (int) env('NOTIFY_RETENTION_DAYS', 90),
];
