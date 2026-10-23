<?php

/**
 * Aturan hierarki struktur organisasi (satu-satunya sumber kebenaran).
 *
 * Kunci = slug jenis unit. `parents` berisi slug jenis induk yang diizinkan;
 * array kosong berarti unit tingkat teratas (tanpa induk). `singleton` berarti
 * hanya boleh ada satu unit dengan jenis tersebut.
 *
 * Jenis unit yang slug-nya tidak tercantum di sini diperlakukan "bebas":
 * boleh berada di bawah unit mana pun yang hierarkinya valid.
 */
return [
    'levels' => [
        'pengurus-besar' => [
            'parents' => [],
            'singleton' => true,
            'short' => 'Pengurus Besar',
            'title' => 'Pengurus Besar',
            'guidance' => 'Pimpinan pusat yayasan. Berada di tingkat paling atas, tidak memiliki unit induk, dan hanya boleh ada satu.',
            'name_label' => 'Nama Pengurus Besar',
            'name_placeholder' => 'Contoh: Pengurus Besar Yayasan Pendidikan',
            'code_placeholder' => 'Contoh: PB-YAYASAN',
        ],
        'komwil' => [
            'parents' => ['pengurus-besar'],
            'short' => 'KOMWIL',
            'title' => 'Komisaris Wilayah (KOMWIL)',
            'guidance' => 'Perwakilan yayasan tingkat provinsi. Induknya harus Pengurus Besar.',
            'name_label' => 'Nama Komisaris Wilayah',
            'name_placeholder' => 'Contoh: KOMWIL Sulawesi Barat',
            'code_placeholder' => 'Contoh: KOMWIL-SULBAR',
        ],
        'komda' => [
            'parents' => ['komwil'],
            'short' => 'KOMDA',
            'title' => 'Komisaris Daerah (KOMDA)',
            'guidance' => 'Perwakilan yayasan tingkat kabupaten/kota. Induknya harus Komisaris Wilayah.',
            'name_label' => 'Nama Komisaris Daerah',
            'name_placeholder' => 'Contoh: KOMDA Kabupaten Mamuju',
            'code_placeholder' => 'Contoh: KOMDA-SULBAR-MJU',
        ],
        'sekolah' => [
            'parents' => ['komda'],
            'short' => 'Sekolah',
            'title' => 'Sekolah',
            'guidance' => 'Satuan pendidikan. Induknya harus Komisaris Daerah yang menaungi lokasi sekolah.',
            'name_label' => 'Nama sekolah',
            'name_placeholder' => 'Contoh: SD Yayasan Palu',
            'code_placeholder' => 'Contoh: SD-SULTENG-PAL',
        ],
    ],
];
