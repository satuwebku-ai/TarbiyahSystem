# 2FA dan CAPTCHA

## Autentikasi dua faktor (2FA)

TOTP (aplikasi autentikator: Google Authenticator, Microsoft Authenticator, Authy, 1Password, dst.).
Berlaku untuk **akun pengelola** (panel) dan **akun pegawai** (portal pegawai). Pelajar tidak memakai 2FA.

| Apa | Di mana |
|---|---|
| Kebijakan (aktif / wajib per jenis akun), ringkasan pemakaian, reset 2FA akun | Pengaturan Admin > Keamanan > **Autentikasi 2 Faktor** (izin `security.view` / `security.update`) |
| Mendaftar / mematikan / kode pemulihan | **Profil Saya** (admin) atau **Profil & Keamanan** (pegawai), oleh pemilik akun |
| Riwayat | Riwayat Keamanan: `*.two_factor_enabled`, `*.two_factor_failed`, `security.two_factor_reset`, dst. |

Alur login: username + kata sandi benar -> halaman kode 6 digit -> sesi dibuat. Sebelum kode benar tidak ada sesi
dan tidak ada cookie "ingat saya". Kode salah dibatasi sesuai "Maksimum percobaan login" di Kebijakan Keamanan;
satu kode tidak bisa dipakai dua kali; tantangan berlaku 5 menit. Kode pemulihan (8, sekali pakai) disimpan sebagai hash.

Mewajibkan 2FA: akun yang belum mendaftar diarahkan ke Profil sampai selesai. Sistem menolak mewajibkan 2FA admin
bila akun yang sedang menyimpan kebijakan itu belum ber-2FA (mencegah mengunci diri sendiri).

Ponsel hilang dan kode pemulihan habis: admin dengan izin `security.update` mereset di halaman 2FA
(akun Super Admin / role inti hanya bisa direset Super Admin), atau dari server:

    php artisan security:recover --two-factor=USERNAME            # akun pengelola
    php artisan security:recover --two-factor=USERNAME --portal=pegawai

Catatan: login satu klik akun demo (`DEMO_LOGIN`) tidak melewati 2FA; pastikan mati di production.

## CAPTCHA

Menu **Pengaturan Aplikasi > CAPTCHA** (izin `settings.update`), atau `CAPTCHA_*` di `.env`.

| Jenis | Keterangan |
|---|---|
| `math` | Pertanyaan hitung buatan sendiri, tanpa akun eksternal. Hanya menahan bot paling sederhana. |
| `turnstile` / `recaptcha` / `hcaptcha` | Butuh site key + secret key dari penyedia dan internet di server (verifikasi) dan di perangkat pengguna. |

Dapat dinyalakan per halaman: login, lupa kata sandi, pendaftaran pelajar. Halaman kode 2FA tidak memakai CAPTCHA.

- CAPTCHA diperiksa **sebelum** hitungan percobaan login, jadi bot tanpa CAPTCHA tidak bisa memblokir akun orang lain.
- Provider eksternal dengan kunci yang belum lengkap **tidak diterapkan** (agar tidak mengunci semua orang); halaman pengaturan menampilkan peringatan.
- Bila layanan verifikasi tidak terjangkau, login ditolak (fail-closed).
- CSP otomatis mengizinkan host penyedia yang dipilih; tanpa CAPTCHA CSP tidak berubah.
- Salah kunci dan terkunci dari login: `php artisan security:recover --captcha`.
