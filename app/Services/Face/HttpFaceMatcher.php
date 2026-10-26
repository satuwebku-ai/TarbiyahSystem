<?php

namespace App\Services\Face;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Layanan pencocok milik sendiri (contoh: folder face-service/). Protokol:
 *   POST {url}  multipart: reference=<berkas>, probe=<berkas>   Header: Authorization: Bearer <token>
 *   200 {"similarity": 0-100}      422 {"error": "no_face"}      lainnya = gagal
 * Layanan tidak boleh menyimpan foto yang diterimanya.
 */
class HttpFaceMatcher implements FaceMatcher
{
    public function __construct(private ?string $url, private ?string $token, private int $timeout = 15) {}

    public function compare(string $reference, string $probe): float
    {
        $this->assertSafeUrl();

        try {
            $response = Http::withToken((string) $this->token)
                ->timeout($this->timeout)
                ->acceptJson()
                ->attach('reference', $reference, 'reference.jpg')
                ->attach('probe', $probe, 'probe.jpg')
                ->post($this->url);
        } catch (Throwable $e) {
            throw new FaceMatchException('Layanan pencocok tidak dapat dihubungi: '.$e->getMessage(), 0, $e);
        }

        if ($response->status() === 422 && $response->json('error') === 'no_face') {
            throw new NoFaceDetectedException('Wajah tidak terdeteksi pada salah satu foto.');
        }
        if (! $response->successful()) {
            throw new FaceMatchException("Layanan pencocok membalas HTTP {$response->status()}.");
        }

        $similarity = $response->json('similarity');
        if (! is_numeric($similarity)) {
            throw new FaceMatchException('Balasan layanan pencocok tidak berisi skor.');
        }

        return max(0.0, min(100.0, (float) $similarity));
    }

    /** HTTP polos hanya untuk layanan di server yang sama; alamat lain wajib HTTPS agar foto tidak terkirim terbuka. */
    private function assertSafeUrl(): void
    {
        $parts = parse_url((string) $this->url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! $host || ! in_array($scheme, ['http', 'https'], true)) {
            throw new FaceMatchException('Alamat layanan pencocok belum diatur atau tidak valid.');
        }
        if ($scheme === 'http' && ! in_array($host, ['127.0.0.1', 'localhost', '[::1]', '::1'], true)) {
            throw new FaceMatchException('Alamat layanan pencocok di luar server ini wajib memakai https://.');
        }
        if (! filled($this->token)) {
            throw new FaceMatchException('Token layanan pencocok belum diisi.');
        }
    }
}
