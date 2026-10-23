<?php

/*
| Proxy tepercaya (reverse proxy / CDN seperti Cloudflare, nginx, dll.).
|
| Dibaca lewat config supaya tetap berlaku setelah `php artisan config:cache`
| (env() di luar folder config/ bernilai null saat config di-cache).
| Dipakai oleh Illuminate\Http\Middleware\TrustProxies agar $request->ip() berisi IP klien asli.
|
| TRUSTED_PROXIES di .env:
|   (kosong)      = tidak ada proxy tepercaya (IP = alamat yang terhubung langsung ke server)
|   10.0.0.5,...  = daftar IP/CIDR proxy, dipisah koma (disarankan)
|   *             = percaya proxy mana pun yang menghubungi server. Hanya aman bila server tidak
|                   bisa dijangkau langsung dari internet (firewall hanya menerima proxy/CDN),
|                   sebab X-Forwarded-For dari klien bisa dipalsukan dan melemahkan pembatasan
|                   IP (login, allowed_ips mesin fingerprint).
*/
return [
    'proxies' => ($proxies = trim((string) env('TRUSTED_PROXIES', ''))) === ''
        ? null
        : ($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies))))),
];
