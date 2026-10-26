<?php

namespace App\Services\Face;

/** Memilih penyedia berdasarkan config('face.driver'); dibaca setiap kali agar perubahan dari menu langsung berlaku. */
class FaceMatcherFactory
{
    public const DRIVERS = ['off', 'http', 'aws'];

    public static function make(?string $driver = null): FaceMatcher
    {
        return match ($driver ?? config('face.driver', 'off')) {
            'http' => new HttpFaceMatcher(config('face.http.url'), config('face.http.token'), (int) config('face.timeout', 15)),
            'aws' => new AwsRekognitionFaceMatcher(config('face.aws.region'), config('face.aws.key'), config('face.aws.secret')),
            default => new NullFaceMatcher,
        };
    }

    public static function enabled(): bool
    {
        return in_array(config('face.driver', 'off'), ['http', 'aws'], true);
    }
}
