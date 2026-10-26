<?php

namespace App\Services\Face;

use RuntimeException;

/** Pencocokan gagal karena penyedia/konfigurasi, bukan karena wajahnya berbeda. */
class FaceMatchException extends RuntimeException {}
