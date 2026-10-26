# Pencocokan wajah absensi pegawai

Fitur ini membandingkan selfie absen dengan foto acuan pegawai dan menyimpan **skor kemiripan (0-100)** pada absen.
Hasilnya hanya **menandai** absen ("cocok", "perlu dicek", "tidak cocok", dst.) agar admin memeriksa; absen **tidak pernah ditolak** otomatis
dan status kehadiran tidak berubah. Bawaannya **mati**: tidak ada foto yang dikirim ke mana pun sebelum Anda memilih penyedia.

## Tiga pilihan penyedia (Pengaturan Aplikasi > Pencocokan Wajah)

| Pilihan | Foto dikirim ke | Cocok bila |
|---|---|---|
| **Mati** | tidak ke mana pun | belum siap / belum ada persetujuan pegawai |
| **Layanan sendiri (HTTP)**: VPS pencocok terpisah | VPS milik sekolah (`https://...`) | aplikasi di shared hosting, ada orang yang bisa merawat satu VPS |
| **Layanan sendiri (HTTP)**: satu VPS dengan aplikasi | server yang sama (`http://127.0.0.1:8099/compare`) | seluruh aplikasi di VPS |
| **Amazon Rekognition** | Amazon (cloud) | ingin paling mudah, tanpa merawat server |

Foto selfie **tetap tersimpan di server aplikasi** pada semua pilihan; yang berbeda hanya ke mana salinannya dikirim untuk dibandingkan.
Pegawai mengabsen seperti biasa; pencocokan berjalan di antrean beberapa detik kemudian (`QUEUE_CONNECTION=database` perlu `queue:work`/cron aktif).

## Langkah umum

1. `php artisan migrate` lalu `php artisan permissions:sync` (menambah izin **Kelola foto acuan wajah pegawai** dan **Periksa foto absensi**); berikan izin itu ke peran yang berhak.
2. Admin mengunggah 1-3 foto acuan per pegawai di halaman detail pegawai (kartu "Foto acuan wajah"). Wajib mencentang persetujuan tertulis pegawai.
   Unggah **langsung oleh admin**; jangan biarkan pegawai mendaftarkan wajahnya sendiri, karena penitip akun bisa mendaftarkan wajah orang lain.
3. Pilih penyedia di Pengaturan Aplikasi > Pencocokan Wajah, isi alamat/kredensial, simpan.
4. Uji dengan satu-dua pegawai dulu, lihat skor di Rekap Harian, baru setel batas skor.

## Memeriksa foto (menu Absensi Pegawai > Periksa Foto)

Halaman ini mengumpulkan absen berfoto dalam cakupan unit Anda dengan thumbnail masuk/pulang, penanda otomatis, dan skor wajah.
Tab **Perlu diperiksa** hanya berisi absen yang punya penanda (foto berciri galeri, atau wajah "perlu dicek / tidak cocok / tidak terdeteksi") dan belum diputuskan.

- **Sesuai** / **Mencurigakan** (catatan wajib) / **Buka kembali**: butuh izin **Periksa foto absensi** (`attendance.photos.review`; jalankan `php artisan permissions:sync` lalu berikan ke peran). Yang memutuskan dan waktunya tercatat; catatan bebas tidak masuk riwayat keamanan.
- Keputusan **hanya dicatat**. Status kehadiran tidak berubah otomatis. Kalau absen perlu dikoreksi, pakai alur Koreksi/Izin yang sudah ada.
- Setelah diputuskan, Rekap Harian menampilkan "Foto diverifikasi" atau "Foto mencurigakan" sebagai ganti "Foto perlu dicek".
- Foto pulang baru pada hari yang sama membatalkan keputusan sebelumnya, karena ada foto yang belum diperiksa.
- **Cocokkan ulang wajah** per absen tersedia bila penyedia aktif.

## Mengulang pencocokan (`php artisan face:rematch`)

| Perintah | Fungsi |
|---|---|
| `php artisan face:rematch` | antrekan ulang foto 30 hari terakhir yang berstatus belum ada foto acuan, gagal, wajah tidak terdeteksi, atau dibuat saat pencocokan masih mati |
| `... --days=7 --status=error --status=no_reference` | batasi jendela waktu dan status yang diulang |
| `... --sync` | jalankan langsung, tidak lewat antrean |
| `... --dry-run` | hanya hitung berapa foto yang akan diproses |
| `... --reclassify` | hitung ulang status "cocok/perlu dicek/tidak cocok" dari skor tersimpan setelah batas skor diubah (tidak memanggil penyedia) |

Setiap hari pukul 04:00 penjadwal otomatis mengulang status `error` dan `no_reference` 3 hari terakhir, hanya bila pencocokan aktif (butuh cron `schedule:run`).
Status "cocok" tidak pernah diulang, dan perintah ini tidak mengubah keputusan admin.

## Menjalankan layanan sendiri (folder `face-service/`)

```bash
cd face-service
python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements.txt
export FACE_SERVICE_TOKEN="$(openssl rand -hex 24)"   # isi token yang sama di Pengaturan Aplikasi
uvicorn app:create_app --factory --host 127.0.0.1 --port 8099
```

- Satu VPS dengan aplikasi: biarkan `127.0.0.1` dan isi alamat `http://127.0.0.1:8099/compare`.
- VPS terpisah: pasang di belakang web server dengan **HTTPS** dan batasi akses firewall ke alamat server aplikasi saja. Aplikasi menolak HTTP polos ke alamat selain server sendiri.
- Layanan tidak menyimpan foto. Tes protokolnya: `python3 -m pytest face-service/test_app.py` (memakai pengenal palsu, tanpa model).
- **Lisensi model:** sebagian model bawaan InsightFace hanya boleh untuk riset/nonkomersial. Periksa sebelum dipakai di sekolah, atau ganti `InsightFaceEmbedder` dengan model berlisensi yang sesuai.
- Pemetaan kosinus ke persen (`FACE_COS_LOW`/`FACE_COS_HIGH`, bawaan 0,2 dan 0,7) hanya titik awal; setel dengan foto pegawai sungguhan.

## Amazon Rekognition

`composer require aws/aws-sdk-php` (jalankan di komputer yang punya Composer lalu unggah folder `vendor/`, bila hosting tidak punya Composer),
isi region dan kunci akses di menu. Gunakan pengguna IAM yang **hanya** punya izin `rekognition:CompareFaces`.
Integrasi ini diuji dengan klien tiruan, **belum** dengan akun AWS sungguhan; coba dengan beberapa foto sebelum diandalkan.
Baca kebijakan data Amazon (lokasi pemrosesan, penggunaan data) sebelum memilihnya.

## Arti status di Rekap Harian

| Tanda | Arti |
|---|---|
| Wajah cocok N% | skor >= batas "cocok" |
| Wajah perlu dicek N% | skor antara batas "perlu dicek" dan "cocok" |
| Wajah tidak cocok N% | skor di bawah batas "perlu dicek" |
| Wajah tidak terdeteksi | penyedia tidak menemukan wajah pada selfie atau foto acuan |
| Belum ada foto acuan | pegawai belum punya foto acuan |
| Pencocokan gagal | penyedia tidak bisa dihubungi setelah 3 percobaan (absen tetap tercatat) |

## Batasan yang perlu diketahui

- **Bukan bukti.** Skor bisa keliru (cahaya, kacamata, masker, perubahan penampilan). Keputusan akhir ada di admin.
- **Tanpa deteksi keaslian (liveness).** Foto cetak atau foto layar HP milik pegawai yang bersangkutan bisa lolos pencocokan.
- Foto yang dicocokkan adalah salinan yang sudah diberi watermark waktu.
- Foto wajah adalah data pribadi/biometrik yang sensitif. Pastikan persetujuan tertulis, tujuan, masa simpan, dan keamanan penyimpanan sesuai aturan perlindungan data pribadi yang berlaku; konsultasikan bila perlu.
- Foto acuan tidak ikut dihapus otomatis oleh pembersih foto absen (`attendance:prune-photos`); hapus manual saat pegawai berhenti.
