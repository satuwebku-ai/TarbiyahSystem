<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\MobileToken;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StudentAccount;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\TwoFactorAuthenticator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Test-Password-2026!';

    // PNG 1x1 valid
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private OrganizationalUnit $unit;

    private AttendancePolicy $policy;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $this->unit = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        $this->policy = AttendancePolicy::create([
            'name' => 'Umum', 'work_days' => [1, 2, 3, 4, 5, 6], 'work_start' => '07:00', 'work_end' => '15:00',
            'late_tolerance_minutes' => 15, 'timezone' => 'Asia/Makassar', 'is_active' => true,
        ]);

        Carbon::setTestNow('2026-10-21 00:30:00'); // Rabu 08:30 WITA
    }

    // ---- login & sesi ---------------------------------------------------------------------------

    public function test_pegawai_can_login_and_logout_with_token(): void
    {
        $user = $this->pegawai();

        $login = $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'PEGAWAI1', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('user.role', 'pegawai')
            ->assertJsonPath('user.identifier', 'pegawai1')
            ->assertJsonPath('user.id', $user->id);

        $token = $login->json('token');
        $this->assertNotSame($token, MobileToken::firstOrFail()->token_hash, 'Teks token tidak boleh tersimpan di database.');
        $this->assertSame(hash('sha256', $token), MobileToken::firstOrFail()->token_hash);

        $this->withToken($token)->getJson('/api/mobile/me')->assertOk()->assertJsonPath('identifier', 'pegawai1');

        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();
        $this->assertSame(0, MobileToken::count());
        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_login_rejects_wrong_password_inactive_and_unlinked_accounts(): void
    {
        $user = $this->pegawai();

        $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => 'salah'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');

        $user->update(['is_active' => false]);
        $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');

        $unlinked = $this->makeAccount('pegawai2');
        $this->assertNull($unlinked->employee);
        $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai2', 'password' => self::PASSWORD])
            ->assertStatus(403)->assertJsonPath('code', 'account_denied');

        $this->assertSame(0, MobileToken::count());
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $this->pegawai();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => 'salah'])->assertStatus(422);
        }

        // Percobaan ke-6 dibatasi, bahkan dengan kata sandi yang benar.
        $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
    }

    public function test_protected_routes_need_a_valid_token_and_the_right_role(): void
    {
        $this->getJson('/api/mobile/employee/attendance/dashboard')->assertUnauthorized();
        $this->withToken('bukan-token')->getJson('/api/mobile/notifications')->assertUnauthorized();

        $student = StudentAccount::create(['name' => 'Siswa Satu', 'nis' => '12345', 'password' => self::PASSWORD, 'is_active' => true]);
        $token = $this->postJson('/api/mobile/login', ['role' => 'pelajar', 'identifier' => '12345', 'password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('user.role', 'pelajar')->json('token');

        $this->withToken($token)->getJson('/api/mobile/employee/attendance/dashboard')
            ->assertForbidden()->assertJsonPath('code', 'wrong_role');
        $this->withToken($token)->getJson('/api/mobile/student/attendance')
            ->assertOk()->assertJsonPath('total', 0)->assertJsonPath('percentage', null);

        // Token pegawai tidak boleh membaca rekap pelajar.
        $this->pegawai();
        $pegawaiToken = $this->login('pegawai1');
        $this->withToken($pegawaiToken)->getJson('/api/mobile/student/attendance')->assertForbidden();

        // Akun dinonaktifkan: token yang sudah ada langsung tidak berlaku.
        $student->update(['is_active' => false]);
        $this->withToken($token)->getJson('/api/mobile/student/attendance')->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->pegawai();
        $token = $this->login('pegawai1');
        MobileToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_login_keeps_only_the_configured_number_of_devices(): void
    {
        config(['mobile.max_tokens_per_account' => 2]);
        $this->pegawai();

        $first = $this->login('pegawai1');
        $this->login('pegawai1');
        $this->login('pegawai1');

        $this->assertSame(2, MobileToken::count());
        $this->withToken($first)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    // ---- absensi pegawai ------------------------------------------------------------------------

    public function test_pegawai_can_check_in_and_out_and_see_history(): void
    {
        $this->pegawai();
        $token = $this->login('pegawai1');

        $this->withToken($token)->getJson('/api/mobile/employee/attendance/dashboard')
            ->assertOk()
            ->assertJsonPath('canCheckIn', true)
            ->assertJsonPath('canCheckOut', false)
            ->assertJsonPath('needsPhoto', false)
            ->assertJsonPath('unit', 'Sekolah A')
            ->assertJsonPath('today', null);

        $in = $this->withToken($token)->postJson('/api/mobile/employee/attendance/check-in', ['latitude' => -0.9, 'longitude' => 119.87, 'accuracy' => 12])
            ->assertOk()->assertJsonPath('record.date', '2026-10-21');
        $this->assertStringStartsWith('2026-10-21T08:30:00+08:00', $in->json('record.checkInAt'));

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame('mobile', $record->source);
        $this->assertEquals(-0.9, (float) $record->check_in_lat);

        $this->withToken($token)->postJson('/api/mobile/employee/attendance/check-in')
            ->assertStatus(422)->assertJsonPath('message', 'Anda sudah check-in hari ini.');

        Carbon::setTestNow('2026-10-21 08:00:00');
        $this->withToken($token)->postJson('/api/mobile/employee/attendance/check-out')->assertOk()->assertJsonPath('record.workMinutes', 450);

        $this->withToken($token)->getJson('/api/mobile/employee/attendance/history?month=2026-10')
            ->assertOk()->assertJsonPath('month', '2026-10')->assertJsonCount(1, 'records');
        $this->withToken($token)->getJson('/api/mobile/employee/attendance/history?month=bukan')->assertStatus(422);
    }

    public function test_photo_is_required_by_policy_and_accepted_as_base64(): void
    {
        $this->policy->update(['require_photo' => true]);
        $this->pegawai();
        $token = $this->login('pegawai1');

        $this->withToken($token)->getJson('/api/mobile/employee/attendance/dashboard')->assertJsonPath('needsPhoto', true);

        $this->withToken($token)->postJson('/api/mobile/employee/attendance/check-in')
            ->assertStatus(422)->assertJsonPath('message', 'Foto selfie wajib diambil untuk absen.');

        $this->withToken($token)->postJson('/api/mobile/employee/attendance/check-in', ['photoBase64' => 'bukan-gambar!!'])->assertStatus(422);
        $this->withToken($token)->postJson('/api/mobile/employee/attendance/check-in', ['photoBase64' => base64_encode('teks biasa, bukan gambar')])
            ->assertStatus(422)->assertJsonPath('message', 'Foto harus berformat JPEG, PNG, atau WebP.');
        $this->assertSame(0, AttendanceRecord::count());

        $this->withToken($token)->postJson('/api/mobile/employee/attendance/check-in', ['photoBase64' => 'data:image/png;base64,'.self::PNG])
            ->assertOk();
        $this->assertNotNull(AttendanceRecord::firstOrFail()->check_in_photo);
    }

    public function test_pegawai_can_submit_and_list_corrections(): void
    {
        $this->pegawai();
        $token = $this->login('pegawai1');

        $this->withToken($token)->postJson('/api/mobile/employee/attendance/corrections', [
            'attendanceDate' => '2026-10-20', 'requestedCheckIn' => '07:05', 'reason' => 'Lupa check-in karena rapat pagi',
        ])->assertCreated()->assertJsonPath('status', 'Menunggu')->assertJsonPath('requestedCheckIn', '07:05');

        $this->withToken($token)->postJson('/api/mobile/employee/attendance/corrections', [
            'attendanceDate' => '2026-10-20', 'requestedCheckIn' => '07:10', 'reason' => 'Pengajuan kedua untuk tanggal sama',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/mobile/employee/attendance/corrections', [
            'attendanceDate' => '2026-10-19', 'requestedCheckIn' => '7pagi', 'reason' => 'Format jam salah',
        ])->assertStatus(422);

        $this->withToken($token)->getJson('/api/mobile/employee/attendance/corrections')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.statusValue', 'pending');
    }

    public function test_ended_employee_loses_access_immediately(): void
    {
        $this->pegawai();
        $token = $this->login('pegawai1');

        Employee::query()->update(['status' => EmployeeStatus::Resigned]);
        $this->withToken($token)->getJson('/api/mobile/employee/attendance/dashboard')
            ->assertForbidden()->assertJsonPath('code', 'account_denied');
    }

    // ---- notifikasi -----------------------------------------------------------------------------

    public function test_notifications_are_listed_and_marked_read_only_for_the_owner(): void
    {
        $a = $this->pegawai();
        $b = $this->makeAccount('pegawai2');
        $this->linkEmployee($b);

        $a->notify(new WorkflowNotification('test', 'Judul A', 'Isi A'));
        $b->notify(new WorkflowNotification('test', 'Judul B', 'Isi B'));

        $tokenA = $this->login('pegawai1');
        $tokenB = $this->login('pegawai2');

        $this->withToken($tokenA)->getJson('/api/mobile/notifications')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'Judul A')->assertJsonPath('0.readAt', null);

        $this->withToken($tokenA)->postJson('/api/mobile/notifications/read', [])->assertOk()->assertJsonPath('updated', 1);

        $this->assertNotNull($this->withToken($tokenA)->getJson('/api/mobile/notifications')->json('0.readAt'));
        $this->assertNull($this->withToken($tokenB)->getJson('/api/mobile/notifications')->json('0.readAt'), 'Notifikasi pegawai lain tidak boleh ikut terbaca.');
    }

    // ---- pendaftaran 2FA dari mobile ------------------------------------------------------------

    public function test_required_2fa_account_enrolls_from_mobile_and_is_signed_in(): void
    {
        $user = $this->pegawai();
        $this->requireTwoFactor();

        // Akun wajib 2FA tidak bisa mendapat token lewat login biasa ...
        $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(403)->assertJsonPath('code', 'two_factor_enrollment_required');
        $this->assertSame(0, MobileToken::count());

        // ... tetapi bisa mendaftar lewat endpoint pendaftaran.
        $setup = $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertOk()->assertJsonStructure(['secret', 'otpauthUri', 'issuer', 'account'])
            ->assertJsonPath('account', 'pegawai1');
        $secret = $setup->json('secret');
        $this->assertStringStartsWith('otpauth://totp/', $setup->json('otpauthUri'));
        $this->assertStringContainsString('secret='.$secret, $setup->json('otpauthUri'));
        $this->assertTrue($user->fresh()->hasPendingTwoFactorSetup());
        $this->assertSame(0, MobileToken::count(), 'Setup saja tidak boleh menghasilkan token.');

        // Kode salah ditolak dan tidak mengaktifkan apa pun.
        $this->postJson('/api/mobile/two-factor/confirm', ['identifier' => 'pegawai1', 'password' => self::PASSWORD, 'code' => '000000'])
            ->assertStatus(422)->assertJsonPath('code', 'two_factor_invalid');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertSame(0, MobileToken::count());

        $confirm = $this->postJson('/api/mobile/two-factor/confirm', [
            'identifier' => 'pegawai1', 'password' => self::PASSWORD, 'code' => $this->totpCode($secret),
        ])->assertOk()->assertJsonPath('user.role', 'pegawai')->assertJsonPath('user.identifier', 'pegawai1');

        $this->assertCount(8, $confirm->json('recoveryCodes'));
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $this->assertSame(1, MobileToken::count());
        $this->assertTrue(AdminAuditLog::where('event', 'pegawai.two_factor_enabled')->exists());

        // Token dari konfirmasi langsung berlaku.
        $this->withToken($confirm->json('token'))->getJson('/api/mobile/me')->assertOk()->assertJsonPath('identifier', 'pegawai1');

        // Login berikutnya meminta kode; kode pemulihan juga diterima (sekali pakai).
        $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(422)->assertJsonPath('code', 'two_factor_required');
        $this->postJson('/api/mobile/login', [
            'role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => self::PASSWORD, 'twoFactorCode' => $confirm->json('recoveryCodes.0'),
        ])->assertOk();
        $this->postJson('/api/mobile/login', [
            'role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => self::PASSWORD, 'twoFactorCode' => $confirm->json('recoveryCodes.0'),
        ])->assertStatus(422)->assertJsonPath('code', 'two_factor_invalid');
    }

    public function test_2fa_enrollment_needs_the_right_password_and_a_linked_active_account(): void
    {
        $user = $this->pegawai();

        $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => 'salah'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'tidak-ada', 'password' => self::PASSWORD])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        $this->assertFalse($user->fresh()->hasPendingTwoFactorSetup());

        $unlinked = $this->makeAccount('pegawai2');
        $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai2', 'password' => self::PASSWORD])
            ->assertStatus(403)->assertJsonPath('code', 'account_denied');
        $this->assertFalse($unlinked->fresh()->hasPendingTwoFactorSetup());

        $user->update(['is_active' => false]);
        $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_2fa_confirm_requires_setup_first_and_cannot_re_enroll_an_active_account(): void
    {
        $user = $this->pegawai();

        $this->postJson('/api/mobile/two-factor/confirm', ['identifier' => 'pegawai1', 'password' => self::PASSWORD, 'code' => '123456'])
            ->assertStatus(422)->assertJsonPath('code', 'two_factor_setup_missing');

        $secret = $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => self::PASSWORD])->json('secret');
        $this->postJson('/api/mobile/two-factor/confirm', ['identifier' => 'pegawai1', 'password' => self::PASSWORD, 'code' => $this->totpCode($secret)])->assertOk();

        // Sudah aktif: setup ulang (yang akan menimpa rahasia) ditolak, begitu juga konfirmasi ulang.
        $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(422)->assertJsonPath('code', 'two_factor_already_enabled');
        $this->assertSame($secret, $user->fresh()->two_factor_secret, 'Rahasia 2FA yang aktif tidak boleh tertimpa.');
    }

    public function test_2fa_enrollment_is_blocked_when_the_feature_is_off(): void
    {
        $this->pegawai();
        AdminSecuritySetting::query()->update(['two_factor_enabled' => false]);
        AdminSecuritySetting::clearCache();

        $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(403)->assertJsonPath('code', 'two_factor_disabled');
    }

    public function test_2fa_confirm_guesses_are_throttled(): void
    {
        $user = $this->pegawai();
        Cache::flush();
        $max = AdminSecuritySetting::current()->max_login_attempts;

        $secret = $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => self::PASSWORD])->json('secret');

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/api/mobile/two-factor/confirm', ['identifier' => 'pegawai1', 'password' => self::PASSWORD, 'code' => '000000'])
                ->assertStatus(422);
        }

        // Setelah batas tercapai, bahkan kode yang benar ditolak sementara.
        $this->postJson('/api/mobile/two-factor/confirm', ['identifier' => 'pegawai1', 'password' => self::PASSWORD, 'code' => $this->totpCode($secret)])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_2fa_enrollment_shares_the_login_lockout(): void
    {
        $this->pegawai();
        Cache::flush();
        $max = AdminSecuritySetting::current()->max_login_attempts;

        for ($i = 0; $i < $max; $i++) {
            $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => 'salah'])->assertStatus(422);
        }

        $this->postJson('/api/mobile/two-factor/setup', ['identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
        $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => 'pegawai1', 'password' => self::PASSWORD])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
    }

    // ---- bantu ----------------------------------------------------------------------------------

    private function requireTwoFactor(): void
    {
        AdminSecuritySetting::query()->update(['two_factor_enabled' => true, 'two_factor_required_pegawai' => true]);
        AdminSecuritySetting::clearCache();
    }

    private function totpCode(string $secret): string
    {
        $totp = app(TwoFactorAuthenticator::class);

        return $totp->codeAt($secret, $totp->currentStep());
    }

    private function login(string $username): string
    {
        return $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => $username, 'password' => self::PASSWORD])
            ->assertOk()->json('token');
    }

    private function pegawai(): User
    {
        return $this->linkEmployee($this->makeAccount('pegawai1'));
    }

    private function makeAccount(string $username): User
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => "pegawai-$n", 'label' => "Pegawai $n", 'guard_name' => 'pegawai']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'attendance.self', 'guard_name' => 'pegawai'], ['label' => 'attendance.self'])->id);
        $user = User::create(['name' => "Pegawai $n", 'username' => $username, 'email' => "$username@example.test", 'password' => self::PASSWORD, 'is_active' => true]);
        $user->syncRoles([$role->id]);

        return $user;
    }

    private function linkEmployee(User $user): User
    {
        $n = ++$this->seq;
        $employee = Employee::create([
            'employee_number' => "E-$n", 'name' => $user->name, 'employee_type' => 'teacher',
            'status' => EmployeeStatus::Active, 'user_id' => $user->id,
        ]);
        $position = OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $this->unit->id, 'name' => 'Guru'], ['is_active' => true]);
        $employee->assignments()->create([
            'organizational_unit_id' => $this->unit->id, 'organizational_position_id' => $position->id, 'is_primary' => true, 'start_date' => '2026-01-01',
        ]);

        return $user;
    }
}
