<?php

namespace App\Http\Controllers\Pelajar;

use App\Enums\StudentAttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\StudentAttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Rekap absensi milik pelajar yang sedang login (hanya data dirinya sendiri, per bulan). */
class AttendanceController extends Controller
{
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __invoke(Request $request)
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $currentMonth = now()->startOfMonth();
        $month = isset($data['month'])
            ? Carbon::createFromFormat('!Y-m', $data['month'])
            : $currentMonth->copy();
        if ($month->gt($currentMonth)) {
            $month = $currentMonth->copy();
        }

        // Selalu dibatasi ke siswa yang tertaut dengan akun ini — tidak ada parameter id dari request.
        $student = auth('pelajar')->user()?->student;

        $records = collect();
        if ($student) {
            $records = StudentAttendanceRecord::query()
                ->join('student_attendance_sessions as sas', 'sas.id', '=', 'student_attendance_records.student_attendance_session_id')
                ->where('student_attendance_records.student_id', $student->id)
                ->whereBetween('sas.attendance_date', [
                    $month->copy()->startOfMonth()->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                ])
                ->orderByDesc('sas.attendance_date')
                ->orderByDesc('sas.id')
                ->select('student_attendance_records.*')
                ->with('session.classroom')
                ->get();
        }

        $counts = [];
        foreach (StudentAttendanceStatus::cases() as $status) {
            $counts[$status->value] = $records->filter(fn ($r) => $r->status === $status)->count();
        }
        $total = $records->count();
        // Terlambat tetap dihitung hadir pada persentase kehadiran.
        $attended = $counts[StudentAttendanceStatus::Present->value] + $counts[StudentAttendanceStatus::Late->value];

        return view('pelajar.attendance', [
            'student'     => $student,
            'records'     => $records,
            'counts'      => $counts,
            'total'       => $total,
            'percentage'  => $total > 0 ? round($attended / $total * 100, 1) : null,
            'month'       => $month,
            'monthLabel'  => self::MONTHS[$month->month].' '.$month->year,
            'prevMonth'   => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth'   => $month->lt($currentMonth) ? $month->copy()->addMonth()->format('Y-m') : null,
            'currentMonth' => $currentMonth->format('Y-m'),
            'statuses'    => StudentAttendanceStatus::cases(),
        ]);
    }
}
