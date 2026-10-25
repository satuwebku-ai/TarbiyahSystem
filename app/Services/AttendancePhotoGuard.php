<?php

namespace App\Services;

/**
 * Pemeriksaan ringan atas foto selfie absen. Portal mengambil foto lewat kamera browser lalu
 * mengecilkannya di canvas (lebar maksimal 640 px, JPEG, tanpa metadata EXIF). Foto yang tidak
 * berciri demikian kemungkinan dipilih dari galeri atau dikirim lewat request manual.
 *
 * Ini PETUNJUK untuk admin, bukan bukti: klien yang sengaja memalsukan tetap bisa meniru ciri tersebut.
 * Yang pasti hanyalah foto identik (hash sama) tidak mungkin berasal dari dua pengambilan kamera.
 */
class AttendancePhotoGuard
{
    /** Lebar maksimum hasil canvas di dashboard pegawai (resources/views/pegawai/dashboard.blade.php). */
    public const MAX_WIDTH = 640;

    public const FLAG_EXIF = 'exif';

    public const FLAG_SIZE = 'ukuran';

    public const FLAG_FORMAT = 'format';

    /** Penjelasan untuk admin per penanda. */
    public const FLAG_LABELS = [
        self::FLAG_EXIF => 'berisi metadata EXIF (hasil kamera portal tidak memilikinya; kemungkinan foto dari galeri)',
        self::FLAG_SIZE => 'lebarnya melebihi '.self::MAX_WIDTH.' px (hasil kamera portal selalu diperkecil)',
        self::FLAG_FORMAT => 'bukan JPEG (kamera portal selalu mengirim JPEG)',
    ];

    /** @return array{hash: string, flags: list<string>} */
    public function inspect(string $path): array
    {
        $flags = [];
        $info = @getimagesize($path);

        if (($info[2] ?? null) !== IMAGETYPE_JPEG) {
            $flags[] = self::FLAG_FORMAT;
        }
        if ($info && ($info[0] ?? 0) > self::MAX_WIDTH) {
            $flags[] = self::FLAG_SIZE;
        }
        if ($this->hasExif($path)) {
            $flags[] = self::FLAG_EXIF;
        }

        return ['hash' => hash_file('sha256', $path), 'flags' => $flags];
    }

    /**
     * Bubuhkan keterangan (waktu server, nama, jenis absen) di bagian bawah foto lalu kembalikan
     * isi JPEG-nya. Null bila GD tidak tersedia atau gambar tidak bisa diproses (simpan aslinya).
     */
    public function stamp(string $path, string $text): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $raw = @file_get_contents($path);
        $img = $raw === false ? false : @imagecreatefromstring($raw);
        if (! $img) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $font = 3;
        $barH = imagefontheight($font) + 8;
        $out = imagecreatetruecolor($w, $h);
        imagecopy($out, $img, 0, 0, 0, 0, $w, $h);

        $black = imagecolorallocatealpha($out, 0, 0, 0, 40);
        imagefilledrectangle($out, 0, $h - $barH, $w, $h, $black);
        $maxChars = max(10, intdiv($w - 8, imagefontwidth($font)));
        imagestring($out, $font, 4, $h - $barH + 4, mb_strimwidth($text, 0, $maxChars, ''), imagecolorallocate($out, 255, 255, 255));

        ob_start();
        imagejpeg($out, null, 80);
        $jpeg = ob_get_clean();
        imagedestroy($img);
        imagedestroy($out);

        return $jpeg ?: null;
    }

    /** Segmen APP1 "Exif" ada pada JPEG yang berasal dari kamera/galeri, tidak pada hasil canvas browser. */
    private function hasExif(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if (! $h) {
            return false;
        }
        $head = (string) fread($h, 65536);
        fclose($h);

        return str_contains($head, "Exif\0\0");
    }
}
