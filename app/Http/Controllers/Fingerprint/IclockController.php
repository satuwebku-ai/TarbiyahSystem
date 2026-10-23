<?php

namespace App\Http\Controllers\Fingerprint;

use App\Http\Controllers\Controller;
use App\Models\FingerprintDevice;
use App\Services\FingerprintAttendanceService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoint "push" (ADMS / iclock) untuk mesin fingerprint ZKTeco, Solution, dan yang sekompatibel.
 * Mesin yang menghubungi server, jadi server tidak perlu bisa menjangkau IP mesin.
 *
 * Di mesin: Menu > Komunikasi > Pengaturan Cloud Server (ADMS)
 *   Alamat server = domain/IP server ini, Port = 80/443 (atau port aplikasi), Proxy mati.
 * Mesin otomatis memanggil:
 *   GET  /iclock/cdata?SN=...        jabat tangan + pengaturan
 *   POST /iclock/cdata?SN=...&table=ATTLOG   log absensi
 *   GET  /iclock/getrequest?SN=...   polling perintah (tidak dipakai, dijawab OK)
 *
 * Tanpa sesi/CSRF/cookie (bukan browser). Keamanan: hanya mesin yang sudah DIAKTIFKAN admin
 * dan dikaitkan ke sekolah yang datanya diterima; mesin tak dikenal hanya tercatat sebagai
 * "perangkat baru" tanpa menyimpan satu pun log. Nomor seri bukan rahasia, jadi di production mesin
 * aktif juga wajib punya daftar IP yang diizinkan (config student_attendance.require_allowed_ips).
 */
class IclockController extends Controller
{
    public function handshake(Request $request): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('ERROR: SN tidak valid', 400);
        }

        $stamp = $device->last_stamp ?: 'None';

        return $this->text(implode("\n", [
            "GET OPTION FROM: {$device->serial_number}",
            "ATTLOGStamp={$stamp}",
            'OPERLOGStamp=None',
            'ATTPHOTOStamp=None',
            'ErrorDelay=60',
            'Delay=30',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            "TransFlag=TransData AttLog\tOpLog",
            'Realtime=1',
            'Encrypt=None',
        ])."\n");
    }

    public function receive(Request $request, FingerprintAttendanceService $service): Response
    {
        $device = $this->device($request);
        if (! $device) {
            return $this->text('ERROR: SN tidak valid', 400);
        }

        $table = strtoupper((string) $request->query('table'));
        if ($table !== 'ATTLOG') {
            // OPERLOG, USERINFO, dll. tidak dipakai; jawab OK agar mesin tidak mengulang.
            return $this->text('OK');
        }

        // Mesin yang belum diaktifkan: tolak (bukan OK) supaya mesin menyimpan lognya dan mengirim ulang nanti.
        if (! $device->acceptsLogs()) {
            return $this->text('ERROR: perangkat belum diaktifkan', 403);
        }

        $body = (string) $request->getContent();
        if (strlen($body) > (int) config('student_attendance.max_body_bytes', 262144)) {
            return $this->text('ERROR: data terlalu besar', 413);
        }

        $punches = $this->parseAttlog($body);
        if (count($punches) > (int) config('student_attendance.max_punches_per_request', 1000)) {
            return $this->text('ERROR: terlalu banyak baris', 413);
        }

        $service->ingest($device, $punches);

        if ($stamp = trim((string) $request->query('Stamp'))) {
            $device->forceFill(['last_stamp' => substr($stamp, 0, 40)])->save();
        }

        return $this->text('OK: '.count($punches));
    }

    public function getRequest(Request $request): Response
    {
        $this->device($request);

        return $this->text('OK');
    }

    public function deviceCmd(Request $request): Response
    {
        $this->device($request);

        return $this->text('OK');
    }

    /**
     * Baris ATTLOG: PIN <tab> yyyy-mm-dd hh:mm:ss <tab> status <tab> verifikasi <tab> workcode ...
     * Beberapa firmware memakai spasi sebagai pemisah, keduanya ditangani.
     *
     * @return array<int, array{pin: string, time: string, status: ?int, verify: ?int}>
     */
    public function parseAttlog(string $body): array
    {
        $punches = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($body)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = str_contains($line, "\t") ? explode("\t", $line) : preg_split('/\s+/', $line);
            if (count($parts) < 2) {
                continue;
            }

            $pin = trim($parts[0]);
            $time = trim($parts[1]);
            $offset = 2;
            // Format spasi: "PIN 2026-10-22 07:01:02 0 1": tanggal & jam terpisah.
            if (! str_contains($time, ' ') && isset($parts[2]) && preg_match('/^\d{2}:\d{2}:\d{2}$/', trim($parts[2]))) {
                $time .= ' '.trim($parts[2]);
                $offset = 3;
            }

            if ($pin === '' || strlen($pin) > 30 || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $time)) {
                continue;
            }

            $punches[] = [
                'pin' => $pin,
                'time' => $time,
                'status' => isset($parts[$offset]) && is_numeric($parts[$offset]) ? (int) $parts[$offset] : null,
                'verify' => isset($parts[$offset + 1]) && is_numeric($parts[$offset + 1]) ? (int) $parts[$offset + 1] : null,
            ];
        }

        return $punches;
    }

    /** Cari/daftarkan mesin dari nomor seri, dan catat kapan terakhir terhubung. */
    private function device(Request $request): ?FingerprintDevice
    {
        $serial = trim((string) ($request->query('SN') ?? $request->input('SN')));
        if ($serial === '' || strlen($serial) > 60 || ! preg_match('/^[A-Za-z0-9._-]+$/', $serial)) {
            return null;
        }

        $device = FingerprintDevice::query()->where('serial_number', $serial)->first();

        // IP dibatasi: tolak sebelum menyentuh DB, jadi permintaan palsu tidak membuat mesin tampak daring.
        if ($device && ! $device->allowsIp($request->ip())) {
            throw new HttpResponseException($this->text('ERROR: IP tidak diizinkan', 403));
        }

        // Mesin aktif wajib dibatasi IP-nya (production): nomor seri saja bukan rahasia.
        if ($device && $device->blockedForMissingIpRestriction()) {
            throw new HttpResponseException($this->text('ERROR: IP mesin belum dibatasi (isi Batasi IP di panel)', 403));
        }

        if (! $device) {
            $pending = FingerprintDevice::query()->whereNull('school_id')->count();
            if ($pending >= (int) config('student_attendance.max_pending_devices', 20)) {
                return null;
            }
            $device = FingerprintDevice::query()->create([
                'serial_number' => $serial,
                'name' => "Perangkat baru {$serial}",
                'is_active' => false,
            ]);
        }

        // Hindari tulis DB di setiap ketukan: cukup bila data sudah usang.
        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subSeconds(30)) || $device->last_ip !== $request->ip()) {
            $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->save();
        }

        return $device;
    }

    private function text(string $body, int $status = 200): Response
    {
        return response($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
