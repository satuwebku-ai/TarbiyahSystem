<?php

namespace App\Services\Face;

use App\Models\AttendanceRecord;
use App\Models\EmployeeFaceReference;
use Illuminate\Support\Facades\Storage;

/**
 * Cocokkan foto selfie sebuah absen dengan foto acuan pegawai, lalu simpan skor + status pada record.
 * Hasilnya hanya penanda untuk admin; status absen tidak pernah diubah dari sini.
 */
class FaceMatchService
{
    public const STATUS_MATCH = 'match';

    public const STATUS_REVIEW = 'review';

    public const STATUS_MISMATCH = 'mismatch';

    public const STATUS_NO_REFERENCE = 'no_reference';

    public const STATUS_NO_FACE = 'no_face';

    public const STATUS_ERROR = 'error';

    public function __construct(private FaceMatcher $matcher) {}

    /**
     * @param  'check_in'|'check_out'  $prefix
     * @return string|null status yang disimpan, null bila tidak ada yang dikerjakan (driver mati / tanpa foto)
     *
     * @throws FaceMatchException bila penyedia gagal; pemanggil (job) yang mengulang atau mencatat 'error'
     */
    public function match(AttendanceRecord $record, string $prefix): ?string
    {
        $path = $record->{"{$prefix}_photo"};
        if (! $path || ! FaceMatcherFactory::enabled()) {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return $this->save($record, $prefix, null, self::STATUS_ERROR);
        }

        $refs = EmployeeFaceReference::query()->where('employee_id', $record->employee_id)
            ->orderBy('id')->limit(max(1, (int) config('face.max_references', 3)))->get();
        if ($refs->isEmpty()) {
            return $this->save($record, $prefix, null, self::STATUS_NO_REFERENCE);
        }

        $probe = $disk->get($path);
        $best = null;
        $noFace = 0;
        foreach ($refs as $ref) {
            if (! $disk->exists($ref->path)) {
                continue;
            }
            try {
                $score = $this->matcher->compare($disk->get($ref->path), $probe);
                $best = max($best ?? 0.0, $score);
            } catch (NoFaceDetectedException) {
                $noFace++;
            }
        }

        if ($best === null) {
            return $this->save($record, $prefix, null, $noFace > 0 ? self::STATUS_NO_FACE : self::STATUS_NO_REFERENCE);
        }

        return $this->save($record, $prefix, round($best, 2), $this->classify($best));
    }

    public function classify(float $score): string
    {
        return match (true) {
            $score >= (float) config('face.match_threshold', 80) => self::STATUS_MATCH,
            $score >= (float) config('face.review_threshold', 60) => self::STATUS_REVIEW,
            default => self::STATUS_MISMATCH,
        };
    }

    private function save(AttendanceRecord $record, string $prefix, ?float $score, string $status): string
    {
        $record->forceFill(["{$prefix}_face_score" => $score, "{$prefix}_face_status" => $status])->saveQuietly();

        return $status;
    }
}
