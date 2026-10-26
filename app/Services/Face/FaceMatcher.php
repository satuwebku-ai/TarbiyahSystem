<?php

namespace App\Services\Face;

/** Kontrak penyedia pencocok wajah: bandingkan dua foto, kembalikan skor kemiripan 0-100. */
interface FaceMatcher
{
    /**
     * @param  string  $reference  isi berkas foto acuan
     * @param  string  $probe  isi berkas foto selfie absen
     * @return float skor 0-100
     *
     * @throws NoFaceDetectedException bila tidak ada wajah pada salah satu foto
     * @throws FaceMatchException bila penyedia gagal (jaringan, kredensial, konfigurasi)
     */
    public function compare(string $reference, string $probe): float;
}
