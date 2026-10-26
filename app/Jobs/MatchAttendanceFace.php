<?php

namespace App\Jobs;

use App\Models\AttendanceRecord;
use App\Services\Face\FaceMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Pencocokan wajah berjalan di antrean agar absen pegawai tidak menunggu penyedia. */
class MatchAttendanceFace implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public int $recordId, public string $prefix) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(FaceMatchService $service): void
    {
        $record = AttendanceRecord::find($this->recordId);
        if ($record) {
            $service->match($record, $this->prefix);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Pencocokan wajah absen gagal.', ['record_id' => $this->recordId, 'part' => $this->prefix, 'error' => $e->getMessage()]);
        AttendanceRecord::whereKey($this->recordId)->update(["{$this->prefix}_face_status" => FaceMatchService::STATUS_ERROR]);
    }
}
