<?php

namespace App\Services\Face;

use Throwable;

/**
 * Amazon Rekognition CompareFaces. Foto dikirim sebagai byte pada setiap permintaan; operasi ini tidak
 * menyimpan foto atau hasilnya di sisi Amazon, tetapi kebijakan data layanan tetap perlu dibaca (lokasi
 * server, penggunaan data). Butuh paket: composer require aws/aws-sdk-php.
 */
class AwsRekognitionFaceMatcher implements FaceMatcher
{
    /** @param object|null $client RekognitionClient, atau pengganti uji dengan method compareFaces(array) */
    public function __construct(
        private ?string $region,
        private ?string $key,
        private ?string $secret,
        private ?object $client = null,
    ) {}

    public function compare(string $reference, string $probe): float
    {
        $client = $this->client ?? $this->makeClient();

        try {
            $result = $client->compareFaces([
                'SourceImage' => ['Bytes' => $reference],
                'TargetImage' => ['Bytes' => $probe],
                'SimilarityThreshold' => 0,
                'QualityFilter' => 'NONE',
            ]);
        } catch (Throwable $e) {
            if (method_exists($e, 'getAwsErrorCode') && $e->getAwsErrorCode() === 'InvalidParameterException') {
                throw new NoFaceDetectedException('Wajah tidak terdeteksi pada salah satu foto.', 0, $e);
            }
            throw new FaceMatchException('Amazon Rekognition gagal: '.$e->getMessage(), 0, $e);
        }

        $matches = $result['FaceMatches'] ?? [];
        if ($matches === []) {
            // Wajah ada di kedua foto tetapi tidak ada yang dianggap sama: skor serendah-rendahnya.
            return 0.0;
        }

        return max(0.0, min(100.0, (float) max(array_map(fn ($m) => (float) ($m['Similarity'] ?? 0), $matches))));
    }

    private function makeClient(): object
    {
        if (! class_exists(\Aws\Rekognition\RekognitionClient::class)) {
            throw new FaceMatchException('Paket aws/aws-sdk-php belum terpasang (composer require aws/aws-sdk-php).');
        }
        if (! filled($this->key) || ! filled($this->secret)) {
            throw new FaceMatchException('Kredensial AWS belum diisi.');
        }

        return $this->client = new \Aws\Rekognition\RekognitionClient([
            'version' => 'latest',
            'region' => $this->region ?: 'ap-southeast-1',
            'credentials' => ['key' => $this->key, 'secret' => $this->secret],
        ]);
    }
}
