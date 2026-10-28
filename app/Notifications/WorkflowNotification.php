<?php

namespace App\Notifications;

use App\Helpers\AttendanceClock;
use App\Models\ActivityReport;
use App\Models\Admin;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\MobileToken;
use App\Notifications\Channels\ExpoPushChannel;
use App\Models\WorkActivity;
use App\Models\WorkProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Satu bentuk notifikasi untuk seluruh alur kerja (program, laporan, koreksi absensi, pengingat).
 * Jenisnya dibedakan oleh $kind; teks dan tautan dibuat di satu tempat lewat pabrik statis di bawah.
 *
 * Tautan disimpan sebagai NAMA ROUTE + parameter, bukan URL jadi, sehingga tetap benar bila domain
 * berubah dan tidak pernah mengarah ke alamat luar. Dikirim lewat antrean: kanal database dan email
 * diproses sebagai pekerjaan terpisah, jadi kegagalan email tidak menggandakan notifikasi di aplikasi.
 */
class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $route = null,
        public array $params = [],
        public string $icon = 'info',
    ) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    /** Pengingat jam absen harian: cukup di aplikasi dan push, tidak perlu email setiap hari. */
    private const NO_MAIL_KINDS = ['reminder.checkin', 'reminder.checkout-time'];

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (config('notifications.mail') && filled($notifiable->email ?? null) && ! in_array($this->kind, self::NO_MAIL_KINDS, true)) {
            $channels[] = 'mail';
        }
        // Push hanya untuk akun pegawai/pelajar yang punya perangkat terdaftar (pengurus/admin tidak).
        if (config('notifications.push') && $notifiable instanceof \Illuminate\Database\Eloquent\Model && MobileToken::pushTokensFor($notifiable) !== []) {
            $channels[] = ExpoPushChannel::class;
        }

        return $channels;
    }

    /** Muatan pesan push Expo. "data" dipakai aplikasi untuk membuka layar yang tepat saat notifikasi diketuk. */
    public function toExpoPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => Str::limit($this->body, 180),
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => 'default',
            'data' => ['kind' => $this->kind, 'screen' => 'notifications'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'route' => $this->route,
            'params' => $this->params,
            'icon' => $this->icon,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo, '.$notifiable->name)
            ->line($this->body);

        if ($url = self::urlFor($this->route, $this->params)) {
            $mail->action('Buka di aplikasi', $url);
        }

        return $mail->salutation(config('app.name'));
    }

    /** URL dari nama route tersimpan; null bila route sudah tidak ada atau parameternya tidak lagi valid. */
    public static function urlFor(?string $route, array $params = []): ?string
    {
        if (! $route || ! Route::has($route)) {
            return null;
        }

        try {
            return route($route, $params);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    // ---- pabrik: program kerja -------------------------------------------------

    public static function programSubmitted(WorkProgram $program, Admin $actor): self
    {
        $program->loadMissing('unit:id,name');

        return new self(
            'program.submitted',
            'Program kerja menunggu persetujuan',
            "{$program->title} ({$program->unit?->name}) diajukan oleh {$actor->name}.",
            'admin.programs.show', [$program->id], 'calendar',
        );
    }

    public static function programReviewed(WorkProgram $program, Admin $reviewer, string $decision, ?string $note): self
    {
        $approved = $decision === 'approved';
        $body = $approved
            ? "{$program->title} disetujui oleh {$reviewer->name}."
            : "{$program->title} dikembalikan oleh {$reviewer->name} untuk direvisi.";
        if (! $approved && $note) {
            $body .= " Catatan: {$note}";
        }

        return new self(
            'program.reviewed',
            $approved ? 'Program kerja disetujui' : 'Program kerja perlu revisi',
            $body,
            'admin.programs.show', [$program->id], $approved ? 'check' : 'alert',
        );
    }

    // ---- pabrik: laporan kegiatan ----------------------------------------------

    public static function reportSubmitted(ActivityReport $report, Admin $actor): self
    {
        $activity = $report->activity;

        return new self(
            'report.submitted',
            'Laporan kegiatan menunggu evaluasi',
            "Laporan {$activity->title} diajukan oleh {$actor->name}.",
            'admin.programs.show', [$activity->work_program_id], 'calendar',
        );
    }

    public static function reportReviewed(ActivityReport $report, Admin $reviewer, string $decision, ?string $note): self
    {
        $activity = $report->activity;
        $ok = $decision === 'reviewed';
        $body = $ok
            ? "Laporan {$activity->title} telah dievaluasi oleh {$reviewer->name}."
            : "Laporan {$activity->title} dikembalikan oleh {$reviewer->name} untuk diperbaiki.";
        if (! $ok && $note) {
            $body .= " Catatan: {$note}";
        }

        return new self(
            'report.reviewed',
            $ok ? 'Laporan kegiatan dievaluasi' : 'Laporan kegiatan perlu perbaikan',
            $body,
            'admin.programs.show', [$activity->work_program_id], $ok ? 'check' : 'alert',
        );
    }

    // ---- pabrik: koreksi absensi -----------------------------------------------

    public static function correctionSubmitted(AttendanceCorrection $correction): self
    {
        $correction->loadMissing('employee:id,name');

        return new self(
            'correction.submitted',
            'Koreksi absensi menunggu persetujuan',
            "{$correction->employee->name} mengajukan koreksi absensi tanggal {$correction->attendance_date->format('d M Y')}.",
            'admin.attendance.corrections.index', [], 'clock',
        );
    }

    public static function correctionReviewed(AttendanceCorrection $correction): self
    {
        $approved = $correction->status->value === 'approved';
        $body = 'Koreksi absensi tanggal '.$correction->attendance_date->format('d M Y').($approved ? ' disetujui.' : ' ditolak.');
        if (! $approved && $correction->review_note) {
            $body .= " Alasan: {$correction->review_note}";
        }

        return new self(
            'correction.reviewed',
            $approved ? 'Koreksi absensi disetujui' : 'Koreksi absensi ditolak',
            $body,
            'pegawai.dashboard', [], $approved ? 'check' : 'alert',
        );
    }

    // ---- pabrik: pengingat terjadwal -------------------------------------------

    public static function pendingApprovals(int $programs, int $reports, int $corrections, int $days): self
    {
        $parts = array_filter([
            $programs ? "{$programs} program kerja" : null,
            $reports ? "{$reports} laporan kegiatan" : null,
            $corrections ? "{$corrections} koreksi absensi" : null,
        ]);

        [$route, $params] = match (true) {
            $programs > 0 => ['admin.programs.index', ['status' => 'submitted']],
            $reports > 0 => ['admin.programs.index', []],
            default => ['admin.attendance.corrections.index', []],
        };

        return new self(
            'reminder.pending',
            'Ada pengajuan yang menunggu keputusan Anda',
            implode(', ', $parts)." belum diproses lebih dari {$days} hari.",
            $route, $params, 'alert',
        );
    }

    public static function activityReminder(WorkActivity $activity, bool $canOpenProgram, int $daysAhead): self
    {
        $start = AttendanceClock::toLocal($activity->planned_start_at);
        $when = $daysAhead === 1 ? 'besok' : "{$daysAhead} hari lagi";
        $body = "{$activity->title} dimulai {$when}, {$start->format('d M Y')} pukul {$start->format('H:i')}"
            .($activity->location ? " di {$activity->location}." : '.');

        return new self(
            'reminder.activity',
            'Pengingat kegiatan',
            $body,
            $canOpenProgram ? 'admin.programs.show' : null,
            $canOpenProgram ? [$activity->work_program_id] : [],
            'calendar',
        );
    }

    public static function missingCheckout(AttendanceRecord $record): self
    {
        return new self(
            'reminder.checkout',
            'Anda belum absen pulang',
            'Absen pulang tanggal '.$record->attendance_date->format('d M Y').' belum tercatat. Ajukan koreksi bila Anda lupa absen.',
            'pegawai.dashboard', [], 'clock',
        );
    }

    /** Pengingat jam absen masuk (push harian bagi pegawai yang belum absen). */
    public static function checkInReminder(string $startTime, bool $started): self
    {
        return new self(
            'reminder.checkin',
            $started ? 'Anda belum absen masuk' : 'Waktunya absen masuk',
            $started
                ? "Jam kerja dimulai pukul {$startTime}. Segera absen masuk."
                : "Jam kerja dimulai pukul {$startTime}. Jangan lupa absen masuk.",
            null, [], 'clock',
        );
    }

    /** Pengingat jam absen pulang untuk pegawai yang sudah absen masuk tetapi belum absen pulang. */
    public static function checkOutReminder(string $endTime): self
    {
        return new self(
            'reminder.checkout-time',
            'Waktunya absen pulang',
            "Jam kerja berakhir pukul {$endTime}. Jangan lupa absen pulang.",
            null, [], 'clock',
        );
    }

    /** Label singkat jenis notifikasi untuk tampilan daftar. */
    public static function kindLabel(?string $kind): string
    {
        return Str::before((string) $kind, '.') === 'reminder' ? 'Pengingat' : 'Pemberitahuan';
    }
}
