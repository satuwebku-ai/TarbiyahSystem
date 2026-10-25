<?php

namespace Tests\Feature;

use App\Services\AttendancePhotoGuard;
use Tests\TestCase;

class AttendancePhotoIntegrityTest extends TestCase
{
    private function jpeg(int $w, int $h, bool $exif = false): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        ob_start();
        imagejpeg($img, null, 70);
        $data = ob_get_clean();
        if ($exif) { // sisipkan segmen APP1 "Exif" tepat setelah penanda SOI, seperti foto kamera/galeri
            $app1 = "\xFF\xE1".pack('n', 8)."Exif\0\0";
            $data = substr($data, 0, 2).$app1.substr($data, 2);
        }
        $path = tempnam(sys_get_temp_dir(), 'ph');
        file_put_contents($path, $data);

        return $path;
    }

    public function test_canvas_like_jpeg_has_no_flags_and_stable_hash(): void
    {
        $path = $this->jpeg(640, 480);
        $r = app(AttendancePhotoGuard::class)->inspect($path);

        $this->assertSame([], $r['flags']);
        $this->assertSame(hash_file('sha256', $path), $r['hash']);
    }

    public function test_gallery_like_photo_is_flagged(): void
    {
        $r = app(AttendancePhotoGuard::class)->inspect($this->jpeg(1600, 1200, exif: true));

        $this->assertEqualsCanonicalizing([AttendancePhotoGuard::FLAG_SIZE, AttendancePhotoGuard::FLAG_EXIF], $r['flags']);
    }

    public function test_non_jpeg_is_flagged_as_format(): void
    {
        $img = imagecreatetruecolor(320, 240);
        $path = tempnam(sys_get_temp_dir(), 'ph');
        imagepng($img, $path);

        $this->assertContains(AttendancePhotoGuard::FLAG_FORMAT, app(AttendancePhotoGuard::class)->inspect($path)['flags']);
    }

    public function test_stamp_returns_jpeg_with_same_dimensions(): void
    {
        $out = app(AttendancePhotoGuard::class)->stamp($this->jpeg(640, 480), 'Budi | MASUK | 2026-10-25 07:00 WITA');

        $this->assertNotNull($out);
        $info = getimagesizefromstring($out);
        $this->assertSame([640, 480, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);
    }

    public function test_stamp_returns_null_for_non_image(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ph');
        file_put_contents($path, 'bukan gambar');

        $this->assertNull(app(AttendancePhotoGuard::class)->stamp($path, 'x'));
    }
}
