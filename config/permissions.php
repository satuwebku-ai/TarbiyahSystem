<?php

/*
| Daftar permission = sumber kebenaran (di kode).
| Role dibuat/diubah lewat UI; permission disinkronkan dengan:
|     php artisan permissions:sync
| Format: guard => grup => [nama => label]
*/
return [

    'admin' => [
        'Dashboard' => [
            'dashboard.view' => 'Lihat dashboard',
        ],
        'Role & Permission' => [
            'roles.view' => 'Lihat role',
            'roles.create' => 'Tambah role',
            'roles.update' => 'Ubah role',
            'roles.delete' => 'Hapus role',
        ],
        'Akun Pengelola' => [
            'admins.view' => 'Lihat akun pengelola',
            'admins.create' => 'Tambah akun pengelola',
            'admins.update' => 'Ubah akun pengelola',
            'admins.delete' => 'Hapus akun pengelola',
        ],
        'Struktur Organisasi' => [
            'organization.view' => 'Lihat struktur organisasi',
            'organization.types.manage' => 'Kelola jenis unit',
            'organization.units.manage' => 'Kelola unit organisasi',
            'organization.positions.manage' => 'Kelola jabatan',
            'organization.assignments.manage' => 'Kelola penugasan',
            'organization.access.manage' => 'Kelola akses unit',
            'organization.access.all' => 'Akses semua unit organisasi',
        ],
        'Kepegawaian' => [
            'employees.view' => 'Lihat data pegawai',
            'employees.create' => 'Daftarkan pegawai',
            'employees.update' => 'Ubah data pokok pegawai',
            'employees.status' => 'Ubah status kepegawaian',
            'employees.assignments.manage' => 'Kelola penempatan pegawai',
            'employees.link_account' => 'Hubungkan pegawai dengan akun pengelola',
            'employees.documents.view' => 'Lihat & unduh dokumen pegawai',
            'employees.documents.manage' => 'Unggah & hapus dokumen pegawai',
            'employees.faces.manage' => 'Kelola foto acuan wajah pegawai (pencocokan wajah absensi)',
            'employees.types.manage' => 'Kelola jenis pegawai',
        ],
        'Absensi Pegawai' => [
            'attendance.view' => 'Lihat rekap absensi (sesuai cakupan unit)',
            'attendance.photos.review' => 'Periksa foto absensi: putuskan sesuai/mencurigakan dan cocokkan ulang wajah',
            'attendance.records.manage' => 'Catat izin, sakit, cuti, tidak hadir',
            'attendance.corrections.review' => 'Setujui/tolak koreksi absensi',
            'attendance.policies.manage' => 'Kelola kebijakan absensi',
            'attendance.holidays.manage' => 'Kelola hari libur',
        ],
        'Program Kerja & Kegiatan' => [
            'programs.view' => 'Lihat program kerja dan kegiatan',
            'programs.manage' => 'Buat dan kelola program/kegiatan sesuai cakupan unit',
            'programs.submit' => 'Ajukan program dan laporan kegiatan',
            'programs.approve' => 'Setujui program dan evaluasi laporan kegiatan',
        ],
        'Data Sekolah & Akademik' => [
            'education.schools.view' => 'Lihat data sekolah sesuai cakupan unit',
            'education.schools.manage' => 'Kelola data sekolah',
            'education.students.view' => 'Lihat data siswa sesuai cakupan unit',
            'education.students.manage' => 'Kelola data siswa, wali, dan penempatan kelas',
            'education.academic.manage' => 'Kelola tahun ajaran dan kelas',
            'education.attendance.view' => 'Lihat rekap absensi siswa sesuai cakupan sekolah',
            'education.attendance.manage' => 'Catat dan ubah absensi siswa sesuai cakupan sekolah',
            'education.attendance.settings' => 'Kelola pengaturan absensi siswa (jam masuk, hari sekolah, alpa otomatis)',
            'education.fingerprint.view' => 'Lihat mesin dan log fingerprint siswa sesuai cakupan sekolah',
            'education.fingerprint.manage' => 'Kelola mesin fingerprint, pemetaan PIN siswa, dan proses ulang log',
        ],
        'Laporan & Dashboard' => [
            'reports.view' => 'Lihat dashboard dan laporan ringkasan sesuai cakupan unit',
            'reports.export' => 'Ekspor (CSV) dan cetak laporan',
        ],
        'Pengaturan Keamanan' => [
            'security.view' => 'Lihat kebijakan keamanan',
            'security.update' => 'Ubah kebijakan keamanan',
            'security.activity' => 'Lihat riwayat keamanan',
        ],
        'Pengaturan Aplikasi' => [
            'settings.view' => 'Lihat pengaturan aplikasi',
            'settings.update' => 'Ubah pengaturan aplikasi (email, pendaftaran, fingerprint)',
        ],
    ],

    // Akun pegawai/guru (tabel users) memakai portal pegawai.<domain>.
    'pegawai' => [
        'Absensi' => [
            'attendance.self' => 'Absen & ajukan koreksi sendiri',
        ],
    ],

    'pelajar' => [
        'Akun' => [
            'profile.view' => 'Lihat profil sendiri',
        ],
    ],
];
