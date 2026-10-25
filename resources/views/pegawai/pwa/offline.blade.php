<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#6366F1">
  <title>Tidak ada koneksi — {{ config('app.name', 'TarbiyahSystem') }}</title>
  <style>
    :root { color-scheme: light dark; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
           font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; text-align: center; background: #f5f6fb; color: #1f2340; }
    .box { max-width: 22rem; }
    .icon { width: 72px; height: 72px; border-radius: 18px; background: linear-gradient(135deg, #4f46e5, #8b5cf6);
            display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
    h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
    p { margin: 0 0 1.25rem; line-height: 1.5; color: #5b6180; }
    button { border: 0; border-radius: .6rem; padding: .7rem 1.4rem; font-size: 1rem; background: #6366f1; color: #fff; }
    @media (prefers-color-scheme: dark) { body { background: #14162a; color: #e8eaf8; } p { color: #a7accb; } }
  </style>
</head>
<body>
  <div class="box">
    <div class="icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="38" height="38" fill="#fff"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
    </div>
    <h1>Tidak ada koneksi internet</h1>
    <p>Absen membutuhkan internet (lokasi, foto, dan pencatatan di server), jadi belum bisa dilakukan sekarang. Sambungkan data seluler atau Wi-Fi, lalu coba lagi.</p>
    <button type="button" onclick="location.href = {{ \Illuminate\Support\Js::from($homeUrl) }}">Coba lagi</button>
  </div>
</body>
</html>
