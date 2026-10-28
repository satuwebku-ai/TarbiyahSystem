<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Admin;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\MobileToken;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\WorkflowNotification;
use App\Services\ReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Test-Password-2026!';

    private const EXPO_TOKEN = 'ExponentPushToken[abc123_DEF-456]';

    private OrganizationalUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $this->unit = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        AttendancePolicy::create([
            'name' => 'Umum', 'work_days' => [1, 2, 3, 4, 5, 6], 'work_start' => '07:00', 'work_end' => '15:00',
            'late_tolerance_minutes' => 15, 'timezone' => 'Asia/Makassar', 'is_active' => true,
        ]);
    }

    // ---- pendaftaran token ----------------------------------------------------------------------

    public function test_device_can_register_and_clear_its_push_token(): void
    {
        [$user, $token] = $this->pegawaiWithToken('pegawai1');

        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN, 'platform' => 'android'])
            ->assertOk()->assertJsonPath('registered', true);
        $this->assertSame([self::EXPO_TOKEN], MobileToken::pushTokensFor($user));

        $this->withToken($token)->deleteJson('/api/mobile/push-token')->assertOk();
        $this->assertSame([], MobileToken::pushTokensFor($user));
    }

    public function test_push_token_must_look_like_an_expo_token_and_needs_login(): void
    {
        [, $token] = $this->pegawaiWithToken('pegawai1');

        $this->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertUnauthorized();
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => 'bukan-token-expo'])->assertStatus(422);
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN, 'platform' => 'windows'])->assertStatus(422);
    }

    public function test_a_device_belongs_to_one_session_when_switching_accounts(): void
    {
        [$first, $firstToken] = $this->pegawaiWithToken('pegawai1');
        [$second, $secondToken] = $this->pegawaiWithToken('pegawai2');

        $this->withToken($firstToken)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();
        $this->withToken($secondToken)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();

        $this->assertSame([], MobileToken::pushTokensFor($first));
        $this->assertSame([self::EXPO_TOKEN], MobileToken::pushTokensFor($second));
    }

    public function test_logout_stops_push_to_that_device(): void
    {
        [$user, $token] = $this->pegawaiWithToken('pegawai1');
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();

        $this->withToken($token)->postJson('/api/mobile/logout')->assertOk();

        $this->assertSame([], MobileToken::pushTokensFor($user));
    }

    // ---- kanal & pengiriman ---------------------------------------------------------------------

    public function test_push_channel_is_added_only_for_accounts_with_a_registered_device(): void
    {
        [$user, $token] = $this->pegawaiWithToken('pegawai1');
        $notification = WorkflowNotification::checkOutReminder('15:00');

        $this->assertNotContains(ExpoPushChannel::class, $notification->via($user));

        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();
        $this->assertContains(ExpoPushChannel::class, $notification->via($user));

        config(['notifications.push' => false]);
        $this->assertNotContains(ExpoPushChannel::class, $notification->via($user));
    }

    public function test_admin_notifications_keep_their_existing_channels(): void
    {
        $admin = new Admin(['name' => 'Admin', 'email' => 'admin@example.test']);

        $this->assertSame(['database', 'mail'], WorkflowNotification::missingCheckout(new AttendanceRecord(['attendance_date' => '2026-10-20']))->via($admin));
    }

    public function test_daily_attendance_reminders_skip_email(): void
    {
        [$user] = $this->pegawaiWithToken('pegawai1');

        $this->assertNotContains('mail', WorkflowNotification::checkInReminder('07:00', false)->via($user));
        $this->assertNotContains('mail', WorkflowNotification::checkOutReminder('15:00')->via($user));
        $this->assertContains('mail', WorkflowNotification::missingCheckout(new AttendanceRecord(['attendance_date' => '2026-10-20']))->via($user));
    }

    public function test_notification_is_sent_to_expo_and_stored_in_the_inbox(): void
    {
        Http::fake([ExpoPushChannel::ENDPOINT => Http::response(['data' => [['status' => 'ok', 'id' => 'x']]])]);
        [$user, $token] = $this->pegawaiWithToken('pegawai1');
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN, 'platform' => 'android'])->assertOk();

        $user->notify(WorkflowNotification::checkOutReminder('15:00'));

        Http::assertSent(fn ($request) => $request->url() === ExpoPushChannel::ENDPOINT
            && $request[0]['to'] === self::EXPO_TOKEN
            && $request[0]['title'] === 'Waktunya absen pulang'
            && $request[0]['data']['screen'] === 'notifications');
        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_unregistered_device_token_is_dropped_after_expo_rejects_it(): void
    {
        Http::fake([ExpoPushChannel::ENDPOINT => Http::response(['data' => [
            ['status' => 'error', 'message' => 'x', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])]);
        [$user, $token] = $this->pegawaiWithToken('pegawai1');
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();

        $user->notify(WorkflowNotification::checkOutReminder('15:00'));

        $this->assertSame([], MobileToken::pushTokensFor($user));
    }

    // ---- pengingat jam absen --------------------------------------------------------------------

    public function test_check_in_reminder_is_sent_once_before_work_starts(): void
    {
        Http::fake([ExpoPushChannel::ENDPOINT => Http::response(['data' => [['status' => 'ok']]])]);
        [$user, $token] = $this->pegawaiWithToken('pegawai1');
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();

        Carbon::setTestNow('2026-10-21 22:50:00'); // Kamis 22 Okt 06:50 WITA, 10 menit sebelum jam masuk
        $this->assertSame(1, app(ReminderService::class)->attendanceTimes());
        $this->assertSame(0, app(ReminderService::class)->attendanceTimes(), 'Tidak boleh terkirim dua kali pada hari yang sama.');

        $this->assertSame('reminder.checkin', $user->notifications()->firstOrFail()->data['kind']);
        Http::assertSentCount(1);
    }

    public function test_no_check_in_reminder_too_early_after_checking_in_on_holiday_or_off_day(): void
    {
        [, $token] = $this->pegawaiWithToken('pegawai1');
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();
        Http::fake();

        Carbon::setTestNow('2026-10-21 21:00:00'); // 05:00 WITA, jauh sebelum jam masuk
        $this->assertSame(0, app(ReminderService::class)->attendanceTimes());

        Carbon::setTestNow('2026-10-24 22:50:00'); // Minggu 25 Okt 06:50 WITA, bukan hari kerja
        $this->assertSame(0, app(ReminderService::class)->attendanceTimes());

        Carbon::setTestNow('2026-10-21 22:50:00'); // Kamis 06:50, tetapi sudah ada catatan hari ini
        $employee = Employee::firstOrFail();
        AttendanceRecord::create([
            'employee_id' => $employee->id, 'organizational_unit_id' => $this->unit->id, 'attendance_date' => '2026-10-22',
            'check_in_at' => '2026-10-21 22:45:00', 'status' => 'present',
        ]);
        $this->assertSame(0, app(ReminderService::class)->attendanceTimes());
    }

    public function test_check_out_reminder_only_for_those_checked_in_without_check_out(): void
    {
        Http::fake([ExpoPushChannel::ENDPOINT => Http::response(['data' => [['status' => 'ok']]])]);
        [$user, $token] = $this->pegawaiWithToken('pegawai1');
        $this->withToken($token)->putJson('/api/mobile/push-token', ['token' => self::EXPO_TOKEN])->assertOk();
        $employee = Employee::firstOrFail();
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id, 'organizational_unit_id' => $this->unit->id, 'attendance_date' => '2026-10-22',
            'check_in_at' => '2026-10-21 23:00:00', 'status' => 'present',
        ]);

        Carbon::setTestNow('2026-10-22 07:05:00'); // 15:05 WITA
        $this->assertSame(1, app(ReminderService::class)->attendanceTimes());
        $this->assertSame('reminder.checkout-time', $user->notifications()->firstOrFail()->data['kind']);

        // Sudah absen pulang: tidak diingatkan lagi.
        Carbon::setTestNow('2026-10-23 07:05:00');
        $record->update(['attendance_date' => '2026-10-23', 'check_out_at' => '2026-10-23 07:00:00']);
        $this->assertSame(0, app(ReminderService::class)->attendanceTimes());
    }

    public function test_attendance_reminders_skip_employees_without_a_registered_device(): void
    {
        $this->pegawaiWithToken('pegawai1'); // login saja, tanpa token push
        Http::fake();

        Carbon::setTestNow('2026-10-21 22:50:00');
        $this->assertSame(0, app(ReminderService::class)->attendanceTimes());
        Http::assertNothingSent();
    }

    // ---- bantu ----------------------------------------------------------------------------------

    /** @return array{0: User, 1: string} */
    private function pegawaiWithToken(string $username): array
    {
        static $seq = 0;
        $n = ++$seq;
        $role = Role::create(['name' => "pegawai-$n-".uniqid(), 'label' => "Pegawai $n", 'guard_name' => 'pegawai']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'attendance.self', 'guard_name' => 'pegawai'], ['label' => 'attendance.self'])->id);
        $user = User::create(['name' => "Pegawai $n", 'username' => $username, 'email' => "$username@example.test", 'password' => self::PASSWORD, 'is_active' => true]);
        $user->syncRoles([$role->id]);

        $employee = Employee::create([
            'employee_number' => "E-$n-".uniqid(), 'name' => $user->name, 'employee_type' => 'teacher',
            'status' => EmployeeStatus::Active, 'user_id' => $user->id,
        ]);
        $position = OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $this->unit->id, 'name' => 'Guru'], ['is_active' => true]);
        $employee->assignments()->create([
            'organizational_unit_id' => $this->unit->id, 'organizational_position_id' => $position->id, 'is_primary' => true, 'start_date' => '2026-01-01',
        ]);

        $token = $this->postJson('/api/mobile/login', ['role' => 'pegawai', 'identifier' => $username, 'password' => self::PASSWORD])
            ->assertOk()->json('token');

        return [$user, $token];
    }
}
