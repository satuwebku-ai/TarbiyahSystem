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

    // Notifikasi yang sudah dibaca dihapus setelah sekian hari.
    'retention_days' => (int) env('NOTIFY_RETENTION_DAYS', 90),
];
