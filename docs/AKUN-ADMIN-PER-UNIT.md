# Akun admin per unit di struktur organisasi

Membuat **satu akun pengelola (admin) untuk tiap unit** di struktur: Pengurus Besar, KOMWIL, KOMDA, dan Sekolah.
Peran mengikuti jenis unit, dan cakupan akun dikunci ke unit itu beserta turunannya (akun KOMDA melihat KOMDA-nya dan sekolah di bawahnya, tidak lebih).

| Jenis unit | Peran yang diberikan |
|---|---|
| Pengurus Besar | `pengurus-besar` |
| KOMWIL | `komisaris-wilayah` |
| KOMDA | `komisaris-daerah` |
| Sekolah | `kepala-sekolah` |

Jenis unit lain (misalnya **Majelis**) tidak punya peran bawaan sehingga tidak dibuatkan akun; buat manual dari Akun Pengelola bila perlu.
Unit nonaktif dilewati, dan unit yang **sudah punya akun berperan sama dilewati** (aman dijalankan berulang, hanya unit baru yang dibuatkan).

## Lewat menu (Manajemen Akses > Akun per Unit)

Hanya **Super Admin**, karena akun yang dibuat memegang peran inti organisasi.

1. Pilih jenis unit dan (opsional) domain email, lalu lihat rencananya: unit mana yang akan dibuat, username, email, dan status.
2. Centang pernyataan bahwa kata sandi hanya tampil sekali, lalu klik **Buat akun**. Opsi **nonaktif** membuat akun mati dulu (aktifkan satu per satu saat diserahkan).
3. Halaman hasil menampilkan username dan **kata sandi sementara sekali saja** (tidak disimpan di server dalam bentuk asli), dengan tombol **Unduh CSV**. Simpan sekarang.

## Lewat terminal

```bash
php artisan admin:provision-units --dry-run                       # lihat rencana saja
php artisan admin:provision-units --types=komwil,komda,sekolah    # buat (minta konfirmasi)
php artisan admin:provision-units --email-domain=sekolah.sch.id --inactive --force
```

Kata sandi sementara ditulis ke `storage/app/private/provisioned-admins-<waktu>.csv` dengan izin `0600`.
**Salin berkas itu ke tempat aman lalu hapus dari server.**

## Aturan pembuatan akun

- **Username** diambil dari kode unit (huruf kecil), misalnya `KOMDA-PALU` menjadi `komda-palu`; bila sudah dipakai ditambah `-2`, `-3`, dst.
- **Email** `username@domain`. Tanpa domain, dipakai `admin.invalid` (domain yang memang tidak bisa menerima surel): ganti ke email asli dari menu Akun Pengelola sebelum akun diserahkan, agar pemulihan kata sandi berfungsi.
- **Kata sandi** acak per akun, memenuhi kebijakan kata sandi di pengaturan keamanan, disimpan ter-hash. Tidak masuk ke riwayat keamanan.
- Pembuatan tercatat di riwayat keamanan (`admin.created` dan `organization.access.updated`, dengan sumber `unit-provisioning`).

## Setelah akun dibuat

1. Serahkan username dan kata sandi lewat jalur aman, satu per pemegang akun (jangan lewat grup obrolan).
2. Minta tiap pemegang akun **mengganti kata sandi**, mengisi **email asli**, dan mengaktifkan **2FA**. Sistem belum memaksa ganti kata sandi pada login pertama.
3. Beri tahu bahwa nama akun ("Admin <nama unit>") adalah akun jabatan; ganti namanya ke nama orangnya dari menu Akun Pengelola bila perlu.
4. Peran yang diberikan adalah peran bawaan; sesuaikan izinnya di menu Role & Permission bila kebijakan Anda berbeda.
