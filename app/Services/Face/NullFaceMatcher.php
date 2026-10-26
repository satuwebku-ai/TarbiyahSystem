<?php

namespace App\Services\Face;

/** Driver "off": tidak pernah dipanggil bila driver mati, dan menolak bila terlanjur dipanggil. */
class NullFaceMatcher implements FaceMatcher
{
    public function compare(string $reference, string $probe): float
    {
        throw new FaceMatchException('Pencocokan wajah dimatikan.');
    }
}
