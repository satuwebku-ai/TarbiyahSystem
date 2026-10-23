# Absensi Siswa via Fingerprint

## Alur
1. `php artisan migrate` lalu `php artisan permissions:sync` (Super Admin otomatis punya semua izin;
   role lain diberi izin dari menu Role & Permission: grup "Data Sekolah & Akademik").
2. Di mesin: **Menu → Komunikasi → Cloud Server (ADMS)** → alamat server = domain/IP aplikasi,
   port 80 (HTTP), aktifkan. Mesin muncul di **Absensi Siswa → Mesin Fingerprint** sebagai
   "Baru terdeteksi"; kaitkan ke sekolah lalu aktifkan.
3. Petakan PIN siswa di **Pemetaan PIN** (massal) atau di form siswa.
4. Atur jam masuk, toleransi, hari sekolah, alpa otomatis di **Pengaturan**
   (umum untuk semua sekolah, bisa ditimpa per sekolah).
5. Alpa otomatis butuh penjadwal: `php artisan schedule:work` (lokal) atau cron `schedule:run` tiap menit.

## Aturan pencatatan
- Ketukan pertama pada hari sekolah dalam jendela jam absen -> Hadir / Terlambat (lewat jam masuk + toleransi).
- Ketukan berikutnya di hari yang sama hanya disimpan sebagai log.
- Catatan manual guru/admin tidak pernah ditimpa mesin.
- Alpa otomatis dapat ditimpa mesin bila siswa mengetuk jari belakangan (tercatat di riwayat koreksi).
- Waktu pada mesin dibaca sebagai zona waktu `ATTENDANCE_TIMEZONE` (bawaan di `config/attendance.php`: Asia/Makassar; isi di `.env` bila sekolah di zona lain).

## Menguji tanpa mesin
```bash
curl "http://localhost/iclock/cdata?SN=TEST001&options=all"
curl -X POST "http://localhost/iclock/cdata?SN=TEST001&table=ATTLOG&Stamp=1" \
     -H "Content-Type: text/plain" --data-binary $'101\t2026-10-21 06:55:00\t0\t1\t0\n'
```
(Mesin harus sudah diaktifkan di panel; sebelum itu POST dijawab 403 dan tidak ada data yang disimpan.)

## Keamanan endpoint `/iclock`
Mesin ADMS hanya mengenali dirinya lewat nomor seri (SN), yang tidak rahasia. Lapisan yang tersedia:
- **Aktivasi admin**: mesin tak dikenal hanya tercatat sebagai "Baru terdeteksi"; log baru diterima setelah diaktifkan.
- **Batasi IP pengirim** (opsional, per mesin): di form mesin isi IP/CIDR dipisah koma, mis. `203.0.113.10, 10.0.0.0/24`.
  Selain itu ditolak 403 dan tidak mengubah status "daring". Jangan dipakai bila IP internet sekolah berubah-ubah
  (isi dulu IP-nya setelah dipastikan tetap), atau batasi di firewall/VPN.
- **Waktu wajar**: ketukan lebih baru dari sekarang + `FINGERPRINT_MAX_FUTURE_MINUTES` (10) atau lebih tua dari
  `FINGERPRINT_MAX_PUNCH_AGE_DAYS` (60) disimpan dengan hasil "Waktu ketukan tidak wajar" dan tidak menjadi absensi
  (biasanya tanda jam mesin salah). Ubah batasnya di `.env` bila mesin bisa offline lebih lama.
- **Ukuran request**: lebih dari `FINGERPRINT_MAX_PUNCHES_PER_REQUEST` (1000) baris atau `FINGERPRINT_MAX_BODY_BYTES`
  (256 KB) dijawab 413 tanpa menyimpan apa pun; mesin akan mencoba lagi. Naikkan bila log mencatat 413 pada mesin sah.
- **Di belakang proxy/CDN**: isi `TRUSTED_PROXIES` di `.env` (IP proxy dipisah koma, atau `*`) agar IP klien dan
  throttle dihitung per mesin, bukan per proxy.
- Mesin umumnya hanya mendukung HTTP (port 80), jadi data tidak terenkripsi di jaringan: pakai pembatasan IP atau VPN.
