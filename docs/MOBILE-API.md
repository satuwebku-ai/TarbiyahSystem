# API Aplikasi Mobile (tarbiyah-mobile)

Aplikasi Expo `tarbiyah-mobile` memakai API JSON ini untuk login, presensi pegawai, rekap kehadiran pelajar,
dan notifikasi. Kodenya: `routes/mobile.php`, `app/Http/Controllers/Api/Mobile/*`, token di tabel `mobile_tokens`.

## Pasang

```bash
php artisan migrate          # membuat tabel mobile_tokens
php artisan route:list --path=api/mobile
```

`.env` (opsional): `MOBILE_TOKEN_TTL_DAYS=30`, `MOBILE_MAX_TOKENS=5` (perangkat aktif per akun; login baru
mencabut token paling lama). Setelah `config:cache`, jalankan ulang cache-nya.

Rute API **tidak terikat domain**: dapat dipanggil dari host mana pun yang menuju aplikasi ini, baik mode path
maupun subdomain. Di aplikasi mobile, isi alamat server dengan alamat utama (tanpa `/api`), misalnya
`https://yayasan.example.com`. Produksi wajib HTTPS.

## Autentikasi

`POST /api/mobile/login` menghasilkan token. Kirim di setiap permintaan lain: `Authorization: Bearer <token>`
dan `Accept: application/json`. Di database hanya hash SHA-256 token yang disimpan.

Token ditolak (`401`, `code: unauthenticated`) bila kedaluwarsa, dicabut (logout), atau akunnya nonaktif.
Syarat portal pegawai dicek ulang di tiap permintaan: akun tanpa data pegawai, status kepegawaian berakhir, izin
`attendance.self` dicabut, atau wajib 2FA tapi belum aktif -> `403`.

Login memakai batas percobaan, kebijakan keamanan, 2FA, dan audit log yang sama dengan portal web (kunci yang sama,
jadi kuncian di web berlaku di mobile dan sebaliknya). CAPTCHA tidak dipakai di API.

## Endpoint

| Metode | Path | Peran | Keterangan |
|---|---|---|---|
| POST | `/login` | - | `{role, identifier, password, twoFactorCode?, deviceName?}` -> `{token, expiresAt, user}` |
| POST | `/two-factor/setup` | - | Pegawai: `{identifier, password}` -> `{secret, otpauthUri, issuer, account}`. Membuat rahasia 2FA baru (belum aktif) |
| POST | `/two-factor/confirm` | - | Pegawai: `{identifier, password, code, deviceName?}` -> `{token, expiresAt, user, recoveryCodes}`. Mengaktifkan 2FA dan langsung masuk |
| GET | `/me` | semua | `{id, name, role, identifier}` |
| POST | `/logout` | semua | Mencabut token ini |
| GET | `/notifications` | semua | 50 terbaru: `[{id, kind, title, body, icon, readAt, createdAt}]` |
| POST | `/notifications/read` | semua | `{ids?: string[]}`; tanpa `ids` = semua dibaca |
| PUT | `/push-token` | semua | `{token, platform?}` (`token` = Expo push token, `platform` = `android`\|`ios`). Mendaftarkan perangkat ini untuk notifikasi push -> `{registered: true}` |
| DELETE | `/push-token` | semua | Berhenti menerima push di perangkat ini (keluar dari akun juga otomatis menghentikannya) |
| GET | `/employee/attendance/dashboard` | pegawai | Status hari ini, kebijakan (`needsPhoto`, `needsLocation`), `canCheckIn`, `canCheckOut`, `recent` |
| GET | `/employee/attendance/history?month=YYYY-MM` | pegawai | `{month, records[]}` |
| POST | `/employee/attendance/check-in` | pegawai | `{latitude?, longitude?, accuracy?, photoBase64?}` -> `{message, record}` |
| POST | `/employee/attendance/check-out` | pegawai | sama dengan check-in |
| GET | `/employee/attendance/corrections` | pegawai | 30 pengajuan terbaru |
| POST | `/employee/attendance/corrections` | pegawai | `{attendanceDate, requestedCheckIn?, requestedCheckOut?, reason}` -> `201` |
| GET | `/student/attendance?month=YYYY-MM` | pelajar | `{month, monthLabel, total, percentage, counts, records[]}` |

`role` pada login: `pegawai` (identifier = username, tabel `users`) atau `pelajar` (identifier = NIS, tabel
`student_accounts`). Akun pengelola (`admins`) tidak bisa masuk lewat API ini.

Presensi memakai `AttendanceService` yang sama dengan portal web, sehingga kebijakan unit, geofence, foto wajib,
hari libur, pencocokan wajah, dan jendela koreksi otomatis berlaku. Catatan dari mobile bertanda `source = mobile`
(tampil "Aplikasi mobile" di presensi harian admin). Foto dikirim sebagai base64 (JPEG/PNG/WebP, maks 2 MB);
jenis file dibaca dari isinya, bukan dari klaim klien. Waktu dikembalikan ISO 8601 dengan offset zona waktu unit.

## Pendaftaran 2FA dari aplikasi

Akun pegawai yang wajib 2FA tetapi belum aktif tidak bisa mendapat token (login menjawab `403 two_factor_enrollment_required`),
sehingga pendaftaran memakai username + kata sandi, bukan token:

1. `POST /two-factor/setup` membuat rahasia baru (menimpa pendaftaran yang belum dikonfirmasi). Aplikasi menampilkan
   `secret` untuk diketik ke aplikasi autentikator, atau membuka `otpauthUri` langsung di ponsel yang sama.
2. `POST /two-factor/confirm` dengan kode 6 digit dari autentikator. Bila benar, 2FA aktif, 8 kode pemulihan dikembalikan
   **sekali saja** (`recoveryCodes`), dan perangkat langsung masuk dengan `token` yang sama seperti hasil login.

Aturan: hanya pegawai; fitur 2FA harus aktif di Kebijakan Keamanan (`403 two_factor_disabled`); akun yang 2FA-nya sudah
aktif ditolak (`422 two_factor_already_enabled`, rahasia lama tidak tertimpa; mematikan atau mengganti 2FA tetap lewat portal web);
`confirm` tanpa `setup` -> `422 two_factor_setup_missing`. Batas percobaan: kata sandi memakai kunci yang sama dengan login,
tebakan kode memakai kunci yang sama dengan tantangan 2FA saat login. Peristiwa dicatat di audit log (`pegawai.two_factor_enabled`, channel `mobile`).

## Notifikasi push

Aplikasi mendaftarkan token push Expo lewat `PUT /push-token` setelah login. Token melekat pada sesi (`mobile_tokens.push_token`),
jadi `POST /logout` atau token kedaluwarsa otomatis menghentikan push ke perangkat itu, dan satu perangkat hanya milik satu sesi.

- Setiap `WorkflowNotification` untuk pegawai/pelajar yang punya perangkat terdaftar dikirim juga lewat `App\Notifications\Channels\ExpoPushChannel`
  (selain kotak masuk). Token yang ditolak Expo (`DeviceNotRegistered`) dilepas otomatis. Matikan dengan `NOTIFY_PUSH=false`.
- Pengingat jam absen: `php artisan reminders:attendance-time` (dijadwalkan tiap 5 menit di `routes/console.php`). Mengirim "Waktunya absen masuk"
  mulai `NOTIFY_CHECKIN_LEAD_MINUTES` (bawaan 15) menit sebelum jam masuk sampai `NOTIFY_REMINDER_WINDOW_MINUTES` (60) menit sesudahnya bila belum ada catatan hari itu,
  dan "Waktunya absen pulang" saat jam pulang bagi yang belum absen pulang. Mengikuti kebijakan, hari kerja, hari libur, dan zona waktu unit pegawai;
  hanya untuk pegawai yang punya perangkat terdaftar; satu kali per hari (kunci cache). Penjadwal wajib berjalan (`schedule:run` lewat cron) begitu juga antrean (`queue:work`).
- Opsional `EXPO_ACCESS_TOKEN` bila akun Expo mengaktifkan keamanan lanjutan untuk push.

## Galat

Semua galat berupa JSON dengan `message` (aman ditampilkan) dan biasanya `code`:

| Status | `code` | Arti |
|---|---|---|
| 401 | `unauthenticated` | Token tidak ada / kedaluwarsa / dicabut |
| 403 | `account_denied`, `wrong_role`, `two_factor_enrollment_required`, `two_factor_disabled` | Akun tidak boleh memakai fitur ini |
| 422 | `invalid_credentials`, `two_factor_required`, `two_factor_invalid`, `two_factor_setup_missing`, `two_factor_already_enabled` | Login gagal / butuh kode 2FA |
| 422 | (validasi) | Aturan absensi dilanggar: `message` berisi alasannya |
| 429 | `too_many_attempts` | Dibatasi; tunggu sesuai pesan |

## Cara mencoba cepat

```bash
curl -s -X POST http://localhost:8000/api/mobile/login -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"role":"pegawai","identifier":"<username>","password":"<kata sandi>"}'
curl -s http://localhost:8000/api/mobile/employee/attendance/dashboard \
  -H 'Accept: application/json' -H "Authorization: Bearer <token>"
```

## Pemeliharaan

Token kedaluwarsa tidak otomatis dihapus dari tabel; bersihkan berkala, mis. di `routes/console.php`:
`Schedule::call(fn () => \App\Models\MobileToken::where('expires_at', '<', now()->subDay())->delete())->daily();`
