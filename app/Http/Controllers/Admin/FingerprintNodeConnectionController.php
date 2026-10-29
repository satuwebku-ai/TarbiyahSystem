<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\FingerprintNodeConnection;
use App\Models\School;
use App\Services\StudentAttendanceAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Username, password, dan token koneksi aplikasi Node fingerprint per sekolah
 * (menu Pengaturan Absensi Siswa). Token asli hanya ditampilkan sekali setelah dibuat.
 */
class FingerprintNodeConnectionController extends Controller
{
    public function edit(Request $request, StudentAttendanceAccess $access, School $school): View
    {
        $school = $this->school($request, $access, $school);

        return view('admin.academic.student-attendance.settings.node', [
            'school' => $school,
            'connection' => FingerprintNodeConnection::query()->where('school_id', $school->id)->first(),
        ]);
    }

    public function update(Request $request, StudentAttendanceAccess $access, School $school): RedirectResponse
    {
        $school = $this->school($request, $access, $school);
        $connection = FingerprintNodeConnection::query()->where('school_id', $school->id)->first();

        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'username' => ['required', 'string', 'min:4', 'max:40', 'regex:/^[a-z0-9._-]+$/', Rule::unique('fingerprint_node_connections', 'username')->ignore($connection?->id)],
            'password' => [$connection ? 'nullable' : 'required', 'string', 'min:10', 'max:100'],
            'allowed_ips' => ['nullable', 'string', 'max:255', $this->ipRule()],
        ], [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, garis bawah, dan strip.',
            'username.unique' => 'Username ini sudah dipakai sekolah lain.',
            'password.required' => 'Password wajib diisi untuk koneksi baru.',
            'password.min' => 'Password minimal 10 karakter.',
        ]);

        $connection ??= new FingerprintNodeConnection(['school_id' => $school->id]);
        $creating = ! $connection->exists;

        $connection->fill([
            'username' => $data['username'],
            'is_active' => $request->boolean('is_active'),
            'allowed_ips' => implode(', ', array_filter(array_map('trim', explode(',', (string) ($data['allowed_ips'] ?? ''))))) ?: null,
        ]);
        if (! empty($data['password'])) {
            $connection->password = $data['password'];
        }

        $token = $creating ? $connection->assignToken() : null;
        $connection->save();

        AdminAuditLog::record('student_attendance.node_saved', $request->user('admin'), $connection, [
            'school_id' => $school->id,
            'created' => $creating,
            'password_changed' => ! empty($data['password']),
        ]);

        $redirect = to_route('admin.academic.student-attendance.settings.index')
            ->with('success', $creating ? 'Koneksi Node fingerprint dibuat. Salin token sekarang.' : 'Koneksi Node fingerprint diperbarui.');

        return $token ? $redirect->with('node_secret', $this->secret($school, $connection, $token)) : $redirect;
    }

    public function regenerateToken(Request $request, StudentAttendanceAccess $access, School $school): RedirectResponse
    {
        $school = $this->school($request, $access, $school);
        $connection = FingerprintNodeConnection::query()->where('school_id', $school->id)->firstOrFail();

        $token = $connection->rotateToken();
        AdminAuditLog::record('student_attendance.node_token_rotated', $request->user('admin'), $connection, ['school_id' => $school->id]);

        return to_route('admin.academic.student-attendance.settings.index')
            ->with('success', 'Token baru dibuat. Token lama langsung tidak berlaku; perbarui aplikasi Node di sekolah.')
            ->with('node_secret', $this->secret($school, $connection, $token));
    }

    public function destroy(Request $request, StudentAttendanceAccess $access, School $school): RedirectResponse
    {
        $school = $this->school($request, $access, $school);
        $deleted = FingerprintNodeConnection::query()->where('school_id', $school->id)->delete();
        if ($deleted) {
            AdminAuditLog::record('student_attendance.node_deleted', $request->user('admin'), $school);
        }

        return to_route('admin.academic.student-attendance.settings.index')
            ->with('success', 'Koneksi Node fingerprint dihapus. Aplikasi Node di sekolah tidak bisa mengirim data lagi.');
    }

    private function school(Request $request, StudentAttendanceAccess $access, School $school): School
    {
        return $access->schools($request->user('admin'))->with('unit')->findOrFail($school->id);
    }

    /** Dikirim lewat flash session: tampil sekali, tidak disimpan di database. */
    private function secret(School $school, FingerprintNodeConnection $connection, string $token): array
    {
        return [
            'school' => $school->unit?->name,
            'username' => $connection->username,
            'token' => $token,
            'url' => url('/api/fingerprint-node'),
        ];
    }

    private function ipRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            foreach (explode(',', (string) $value) as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }
                [$ip, $mask] = array_pad(explode('/', $entry, 2), 2, null);
                $max = str_contains((string) $ip, ':') ? 128 : 32;
                if (filter_var($ip, FILTER_VALIDATE_IP) === false || ($mask !== null && (! ctype_digit($mask) || (int) $mask > $max))) {
                    $fail("Alamat IP \"{$entry}\" tidak valid. Gunakan IP atau CIDR dipisah koma, mis. 203.0.113.10, 10.0.0.0/24.");

                    return;
                }
            }
        };
    }
}
