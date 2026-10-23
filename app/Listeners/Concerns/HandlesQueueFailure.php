<?php

namespace App\Listeners\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Perilaku bersama listener antrean: coba ulang bertahap, lewati bila model sudah dihapus
 * (tidak ada gunanya diulang), dan catat kegagalan akhir dengan jelas di log.
 */
trait HandlesQueueFailure
{
    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function backoff(): array
    {
        return [30, 120];
    }

    public function failed(object $event, Throwable $exception): void
    {
        Log::error('Listener notifikasi gagal setelah semua percobaan.', [
            'listener' => static::class,
            'event' => $event::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
