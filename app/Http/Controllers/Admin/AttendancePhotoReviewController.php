<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AttendanceClock;
use App\Http\Controllers\Controller;
use App\Jobs\MatchAttendanceFace;
use App\Models\AdminAuditLog;
use App\Models\AttendanceRecord;
use App\Services\Face\FaceMatcherFactory;
use App\Services\OrganizationAccess;
use App\Services\SchoolScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Daftar foto absen untuk diperiksa admin: tandai "sesuai" atau "mencurigakan" (dengan catatan), buka kembali,
 * atau cocokkan ulang wajah. Keputusan hanya dicatat; status kehadiran pegawai tidak diubah dari sini.
 */
class AttendancePhotoReviewController extends Controller
{
    public const STATUSES = ['perlu' => 'Perlu diperiksa', 'ok' => 'Sesuai', 'suspicious' => 'Mencurigakan', 'semua' => 'Semua berfoto'];

    public function index(Request $request, SchoolScope $schools): View
    {
        $admin = $request->user('admin');
        $status = array_key_exists((string) $request->query('status'), self::STATUSES) ? (string) $request->query('status') : 'perlu';
        $to = $this->date((string) $request->query('to')) ?? AttendanceClock::today();
        $from = $this->date((string) $request->query('from')) ?? $to->copy()->subDays(6);
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $filters = $schools->filters($request);
        $unitScope = $schools->unitScope($admin, $filters);

        $base = fn (): Builder => AttendanceRecord::query()
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($w) => $w->whereNotNull('check_in_photo')->orWhereNotNull('check_out_photo'))
            ->when($unitScope !== null, fn ($q) => $q->whereIn('organizational_unit_id', $unitScope));

        $counts = [
            'perlu' => $base()->needsPhotoReview()->count(),
            'ok' => $base()->where('photo_review_status', AttendanceRecord::REVIEW_OK)->count(),
            'suspicious' => $base()->where('photo_review_status', AttendanceRecord::REVIEW_SUSPICIOUS)->count(),
            'semua' => $base()->count(),
        ];

        /** @var LengthAwarePaginator $records */
        $records = $base()
            ->when($status === 'perlu', fn ($q) => $q->needsPhotoReview())
            ->when($status === 'ok', fn ($q) => $q->where('photo_review_status', AttendanceRecord::REVIEW_OK))
            ->when($status === 'suspicious', fn ($q) => $q->where('photo_review_status', AttendanceRecord::REVIEW_SUSPICIOUS))
            ->with(['employee:id,name,employee_number', 'photoReviewer:id,name'])
            ->orderByDesc('attendance_date')->orderByDesc('id')
            ->paginate(24)->withQueryString();

        return view('admin.attendance.photos', [
            'records' => $records, 'status' => $status, 'counts' => $counts, 'from' => $from, 'to' => $to,
            'scope' => $schools->options($admin), 'filters' => $filters,
            'faceEnabled' => FaceMatcherFactory::enabled(),
        ]);
    }

    public function review(Request $request, AttendanceRecord $record, OrganizationAccess $org): RedirectResponse
    {
        $admin = $request->user('admin');
        $this->authorizeRecord($admin, $record, $org);

        $data = $request->validate([
            'decision' => ['required', 'in:ok,suspicious,reset'],
            'note' => ['nullable', 'required_if:decision,suspicious', 'string', 'max:500'],
        ], ['note.required_if' => 'Isi catatan singkat alasan foto dinilai mencurigakan.']);

        $record->forceFill($data['decision'] === 'reset'
            ? ['photo_review_status' => null, 'photo_review_note' => null, 'photo_reviewed_by_admin_id' => null, 'photo_reviewed_at' => null]
            : ['photo_review_status' => $data['decision'], 'photo_review_note' => $data['note'] ?? null,
                'photo_reviewed_by_admin_id' => $admin->id, 'photo_reviewed_at' => now()])->save();

        // Catatan bebas tidak ikut ke riwayat keamanan; cukup keputusan dan rujukan absennya.
        AdminAuditLog::record('attendance.photo_reviewed', $admin, $record, ['decision' => $data['decision'], 'employee_id' => $record->employee_id]);

        return back()->with('success', match ($data['decision']) {
            'ok' => 'Foto ditandai sesuai.',
            'suspicious' => 'Foto ditandai mencurigakan.',
            default => 'Keputusan dibuka kembali.',
        });
    }

    public function rematch(Request $request, AttendanceRecord $record, OrganizationAccess $org): RedirectResponse
    {
        $this->authorizeRecord($request->user('admin'), $record, $org);

        if (! FaceMatcherFactory::enabled()) {
            return back()->withErrors(['face' => 'Pencocokan wajah sedang dimatikan (Pengaturan Aplikasi > Pencocokan Wajah).']);
        }

        $queued = 0;
        foreach (['check_in', 'check_out'] as $prefix) {
            if ($record->{"{$prefix}_photo"}) {
                MatchAttendanceFace::dispatch($record->id, $prefix);
                $queued++;
            }
        }

        return $queued
            ? back()->with('success', 'Pencocokan wajah diulang; hasilnya muncul beberapa saat lagi.')
            : back()->withErrors(['face' => 'Absen ini tidak punya foto untuk dicocokkan.']);
    }

    private function authorizeRecord($admin, AttendanceRecord $record, OrganizationAccess $org): void
    {
        $record->organizational_unit_id
            ? $org->assertCanAccess($admin, (int) $record->organizational_unit_id)
            : abort_unless($org->visibleUnitIds($admin) === null, 403);
    }

    private function date(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? Carbon::parse($value, AttendanceClock::tz())->startOfDay()
            : null;
    }
}
