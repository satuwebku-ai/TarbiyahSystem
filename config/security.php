<?php

/*
| Header keamanan HTTP untuk seluruh halaman web (admin, pegawai, pelajar).
| Mesin fingerprint (/iclock) tidak melewatinya karena bukan browser.
|
| SECURITY_HEADERS=false  : matikan semua header ini (mis. bila sudah diatur di web server).
| SECURITY_CSP            : enforce (bawaan) | report-only (hanya melapor di konsol browser) | off
| SECURITY_HSTS=false     : jangan kirim HSTS. HSTS hanya dikirim di production lewat HTTPS.
*/
return [
    'headers' => filter_var(env('SECURITY_HEADERS', true), FILTER_VALIDATE_BOOLEAN),

    'csp' => in_array($csp = strtolower((string) env('SECURITY_CSP', 'enforce')), ['enforce', 'report-only', 'off'], true) ? $csp : 'enforce',

    'hsts' => filter_var(env('SECURITY_HSTS', true), FILTER_VALIDATE_BOOLEAN),
    'hsts_max_age' => 15552000, // 180 hari; tanpa includeSubDomains/preload agar subdomain lain tidak ikut terkunci.

    // Host eksternal yang dimuat tampilan (Bootstrap, jQuery, Select2, DataTables). Tambahkan di sini bila menambah CDN baru.
    'sources' => [
        'script' => ['https://cdn.jsdelivr.net', 'https://code.jquery.com', 'https://cdn.datatables.net'],
        'style' => ['https://cdn.jsdelivr.net', 'https://cdn.datatables.net'],
        'font' => ['https://cdn.jsdelivr.net'],
    ],
];
