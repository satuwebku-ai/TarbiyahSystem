<?php

/*
| Pencocokan wajah selfie absen dengan foto acuan pegawai. Semua nilai dapat diubah dari menu
| Pengaturan Aplikasi > Pencocokan Wajah (nilai di menu menimpa .env).
|
| Driver:
|   off  : mati (bawaan). Tidak ada foto yang dikirim ke mana pun.
|   http : layanan pencocok milik sendiri lewat HTTP (lihat face-service/). Dipakai untuk "VPS pencocok
|          terpisah" (URL https://...) maupun "aplikasi dan pencocok di satu VPS" (URL http://127.0.0.1:8099).
|   aws  : Amazon Rekognition CompareFaces (butuh composer require aws/aws-sdk-php).
|
| Skor kemiripan memakai skala 0-100. Hasil hanya MENANDAI absen untuk diperiksa admin; absen tidak pernah ditolak.
*/
return [
    'driver' => env('FACE_DRIVER', 'off'),

    // Skor >= match_threshold = "cocok"; skor >= review_threshold = "perlu dicek"; di bawahnya "tidak cocok".
    // Angka bawaan hanya titik awal: setel dengan foto pegawai sungguhan di sekolah Anda.
    'match_threshold' => (int) env('FACE_MATCH_THRESHOLD', 80),
    'review_threshold' => (int) env('FACE_REVIEW_THRESHOLD', 60),

    // Batas waktu satu permintaan ke penyedia (detik).
    'timeout' => (int) env('FACE_TIMEOUT', 15),

    // Jumlah maksimum foto acuan per pegawai.
    'max_references' => 3,

    'http' => [
        'url' => env('FACE_HTTP_URL'),
        'token' => env('FACE_HTTP_TOKEN'),
    ],

    'aws' => [
        'region' => env('FACE_AWS_REGION', 'ap-southeast-1'),
        'key' => env('FACE_AWS_KEY'),
        'secret' => env('FACE_AWS_SECRET'),
    ],
];
