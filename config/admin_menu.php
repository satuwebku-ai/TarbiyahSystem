<?php

/*
| Menu sidebar panel pengelola (sumber kebenaran tampilan menu).
| Struktur : bagian (title) -> item. Item biasa : label, icon, route, active (pola routeIs), except (pola routeIs
|            yang dikecualikan dari active, mis. menu lain di bawah prefix yang sama; opsional), can (opsional).
|            Item grup (submenu) : label, icon, children[] (tiap anak: label, route, active, can).
| Item tanpa izin ('can') tampil untuk semua pengelola; bagian/grup yang semua isinya
| disembunyikan oleh izin otomatis tidak ditampilkan.
| Menu baru: tambahkan baris di sini + route + permission di config/permissions.php.
*/
return [
    [
        'title' => 'Utama',
        'items' => [
            ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'can' => 'dashboard.view'],
            ['label' => 'Notifikasi', 'icon' => 'bell', 'route' => 'admin.notifications.index', 'active' => 'admin.notifications.*'],
        ],
    ],
    [
        'title' => 'Organisasi',
        'items' => [
            ['label' => 'Struktur Organisasi', 'icon' => 'grid', 'children' => [
                ['label' => 'Ikhtisar', 'route' => 'admin.organization.index', 'active' => 'admin.organization.index', 'can' => 'organization.view'],
                ['label' => 'Unit Organisasi', 'route' => 'admin.organization.units.index', 'active' => 'admin.organization.units.*', 'can' => 'organization.view'],
                ['label' => 'Jenis Unit', 'route' => 'admin.organization.unit-types.index', 'active' => 'admin.organization.unit-types.*', 'can' => 'organization.types.manage'],
                ['label' => 'Jabatan', 'route' => 'admin.organization.positions.index', 'active' => 'admin.organization.positions.*', 'can' => 'organization.positions.manage'],
                ['label' => 'Penugasan', 'route' => 'admin.organization.assignments.index', 'active' => 'admin.organization.assignments.*', 'can' => 'organization.assignments.manage'],
                ['label' => 'Akses Unit', 'route' => 'admin.organization.access.index', 'active' => 'admin.organization.access.*', 'can' => 'organization.access.manage'],
            ]],
        ],
    ],
    [
        'title' => 'Kepegawaian',
        'items' => [
            ['label' => 'Data Pegawai', 'icon' => 'users', 'route' => 'admin.employees.index', 'active' => 'admin.employees.*', 'except' => 'admin.employees.types.*', 'can' => 'employees.view'],
            ['label' => 'Jenis Pegawai', 'icon' => 'users', 'route' => 'admin.employees.types.index', 'active' => 'admin.employees.types.*', 'can' => 'employees.types.manage'],
        ],
    ],
    [
        'title' => 'Program Kerja',
        'items' => [
            ['label' => 'Program & Kegiatan', 'icon' => 'calendar', 'route' => 'admin.programs.index', 'active' => 'admin.programs.*', 'can' => 'programs.view'],
        ],
    ],
    [
        'title' => 'Pendidikan',
        'items' => [
            ['label' => 'Data Sekolah',        'icon' => 'school',   'route' => 'admin.academic.schools.index',                 'active' => 'admin.academic.schools.*',                    'can' => 'education.schools.view'],
            ['label' => 'Data Siswa',          'icon' => 'graduate', 'route' => 'admin.academic.students.index',                'active' => 'admin.academic.students.*',                   'can' => 'education.students.view'],
        ],
    ],
    [
        'title' => 'Absensi Siswa',
        'items' => [
            ['label' => 'Absensi Siswa', 'icon' => 'clipboard', 'children' => [
                ['label' => 'Input Absensi', 'route' => 'admin.academic.student-attendance.index', 'active' => 'admin.academic.student-attendance.index', 'can' => 'education.attendance.manage'],
                ['label' => 'Rekap Absensi', 'route' => 'admin.academic.student-attendance.report', 'active' => 'admin.academic.student-attendance.report', 'can' => 'education.attendance.view'],
                ['label' => 'Mesin Fingerprint', 'route' => 'admin.academic.fingerprint.devices.index', 'active' => 'admin.academic.fingerprint.devices.*', 'can' => 'education.fingerprint.view'],
                ['label' => 'Pemetaan PIN', 'route' => 'admin.academic.fingerprint.pins.index', 'active' => 'admin.academic.fingerprint.pins.*', 'can' => 'education.fingerprint.manage'],
                ['label' => 'Log Fingerprint', 'route' => 'admin.academic.fingerprint.logs.index', 'active' => 'admin.academic.fingerprint.logs.*', 'can' => 'education.fingerprint.view'],
                ['label' => 'Pengaturan', 'route' => 'admin.academic.student-attendance.settings.index', 'active' => 'admin.academic.student-attendance.settings.*', 'can' => 'education.attendance.settings'],
            ]],
        ],
    ],
    [
        'title' => 'Absensi',
        'items' => [
            ['label' => 'Absensi Pegawai', 'icon' => 'calendar', 'children' => [
                ['label' => 'Rekap Harian', 'route' => 'admin.attendance.daily', 'active' => 'admin.attendance.daily', 'can' => 'attendance.view'],
                ['label' => 'Rekap Bulanan', 'route' => 'admin.attendance.monthly', 'active' => 'admin.attendance.monthly', 'can' => 'attendance.view'],
                ['label' => 'Koreksi', 'route' => 'admin.attendance.corrections.index', 'active' => 'admin.attendance.corrections.*', 'can' => 'attendance.corrections.review'],
                ['label' => 'Izin / Sakit / Cuti', 'route' => 'admin.attendance.entries.index', 'active' => 'admin.attendance.entries.*', 'can' => 'attendance.records.manage'],
                ['label' => 'Kebijakan', 'route' => 'admin.attendance.policies.index', 'active' => 'admin.attendance.policies.*', 'can' => 'attendance.policies.manage'],
                ['label' => 'Hari Libur', 'route' => 'admin.attendance.holidays.index', 'active' => 'admin.attendance.holidays.*', 'can' => 'attendance.holidays.manage'],
            ]],
        ],
    ],
    [
        'title' => 'Laporan',
        'items' => [
            ['label' => 'Dashboard & Laporan', 'icon' => 'grid', 'route' => 'admin.reports.index', 'active' => 'admin.reports.*', 'can' => 'reports.view'],
        ],
    ],
    [
        'title' => 'Manajemen Akses',
        'items' => [
            ['label' => 'Akses & Pengguna', 'icon' => 'shield', 'children' => [
                ['label' => 'Akun Pengelola',   'route' => 'admin.admins.index', 'active' => 'admin.admins.*', 'can' => 'admins.view'],
                ['label' => 'Role & Permission', 'route' => 'admin.roles.index',  'active' => 'admin.roles.*',  'can' => 'roles.view'],
            ]],
        ],
    ],
    [
        'title' => 'Pengaturan Admin',
        'items' => [
            ['label' => 'Keamanan', 'icon' => 'lock', 'children' => [
                ['label' => 'Kebijakan Keamanan', 'route' => 'admin.security.index', 'active' => 'admin.security.index', 'can' => 'security.view'],
                ['label' => 'Autentikasi 2 Faktor', 'route' => 'admin.security.two-factor', 'active' => 'admin.security.two-factor*', 'can' => 'security.view'],
                ['label' => 'Riwayat Keamanan', 'route' => 'admin.security.activity', 'active' => 'admin.security.activity', 'can' => 'security.activity'],
            ]],
            ['label' => 'Pengaturan Aplikasi', 'icon' => 'bolt', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*', 'can' => 'settings.view'],
        ],
    ],
    [
        'title' => 'Akun',
        'items' => [
            ['label' => 'Profil Saya', 'icon' => 'user', 'route' => 'admin.profile.edit', 'active' => 'admin.profile.*'],
        ],
    ],
];
