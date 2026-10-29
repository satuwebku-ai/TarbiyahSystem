<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Admin;
use App\Models\Classroom;
use App\Models\FingerprintLog;
use App\Models\FingerprintNodeConnection;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolLevel;
use App\Models\Student;
use App\Models\StudentClassPlacement;
use App\Models\StudentEnrollment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FingerprintNodeTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Student $andi;

    private FingerprintNodeConnection $connection;

    private string $token;

    private const PASSWORD = 'Node-Secret-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 02:00:00');

        $root = OrganizationalUnit::query()->create([
            'type_id' => OrganizationalUnitType::query()->create(['name' => 'Yayasan', 'slug' => 'yayasan', 'is_active' => true])->id,
            'code' => 'Y', 'name' => 'Yayasan', 'status' => 'active',
        ]);
        $unit = OrganizationalUnit::query()->create([
            'type_id' => OrganizationalUnitType::query()->create(['name' => 'Sekolah', 'slug' => 'sekolah', 'is_active' => true])->id,
            'parent_id' => $root->id, 'code' => 'S1', 'name' => 'SD Satu', 'status' => 'active',
        ]);
        $level = SchoolLevel::query()->create(['name' => 'SD', 'slug' => 'sd', 'sort_order' => 1, 'is_active' => true]);
        $this->school = School::query()->create(['organizational_unit_id' => $unit->id, 'school_level_id' => $level->id, 'status' => 'active']);
        $year = AcademicYear::query()->create(['school_id' => $this->school->id, 'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $classroom = Classroom::query()->create(['school_id' => $this->school->id, 'academic_year_id' => $year->id, 'name' => '1A', 'grade_label' => '1', 'status' => 'active']);

        $this->andi = Student::query()->create(['school_id' => $this->school->id, 'nis' => 'NIS-101', 'name' => 'Andi', 'status' => 'active', 'fingerprint_pin' => '101']);
        $enrollment = StudentEnrollment::query()->create(['student_id' => $this->andi->id, 'academic_year_id' => $year->id, 'enrollment_date' => '2026-07-01', 'status' => 'active']);
        StudentClassPlacement::query()->create(['student_enrollment_id' => $enrollment->id, 'classroom_id' => $classroom->id, 'start_date' => '2026-07-01', 'status' => 'active']);

        $this->connection = new FingerprintNodeConnection(['school_id' => $this->school->id, 'username' => 'sd-satu', 'is_active' => true]);
        $this->connection->password = self::PASSWORD;
        $this->token = $this->connection->assignToken();
        $this->connection->save();
    }

    private function headers(?string $password = null, ?string $token = null, string $user = 'sd-satu'): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode($user.':'.($password ?? self::PASSWORD)),
            'X-Node-Token' => $token ?? $this->token,
        ];
    }

    private function punch(array $headers, string $time = '2026-10-21 06:55:00')
    {
        return $this->withHeaders($headers)->postJson('/api/fingerprint-node/punches', [
            'device_sn' => 'MESIN1',
            'punches' => [['pin' => '101', 'time' => $time, 'status' => 0, 'verify' => 1]],
        ]);
    }

    private function superAdmin(): Admin
    {
        $role = Role::query()->create(['name' => 'super-admin', 'label' => 'Super Admin', 'guard_name' => 'admin', 'is_system' => true]);
        $admin = Admin::query()->create(['name' => 'SA', 'username' => 'sa-node', 'email' => 'sa-node@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    public function test_valid_credentials_connect_and_punch_becomes_attendance(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/fingerprint-node/connect')
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('school.name', 'SD Satu');

        $this->punch($this->headers())->assertOk()->assertJsonPath('created', 1);
        $this->assertDatabaseHas('student_attendance_records', ['student_id' => $this->andi->id, 'status' => 'present', 'source' => 'fingerprint']);
        $this->assertDatabaseHas('fingerprint_devices', ['serial_number' => "NODE{$this->connection->id}-MESIN1", 'school_id' => $this->school->id, 'is_active' => 1]);

        // Kirim ulang log yang sama tidak menggandakan.
        $this->punch($this->headers())->assertOk()->assertJsonPath('created', 0);
        $this->assertSame(1, FingerprintLog::query()->count());
        $this->assertSame(1, $this->connection->fresh()->total_logs);
    }

    public function test_wrong_password_token_or_username_is_rejected(): void
    {
        $this->punch($this->headers('salah-total'))->assertStatus(401);
        $this->punch($this->headers(null, 'tbn_tokensalah'))->assertStatus(401);
        $this->punch($this->headers(null, null, 'orang-lain'))->assertStatus(401);
        $this->postJson('/api/fingerprint-node/punches', ['punches' => []])->assertStatus(401);
        $this->assertSame(0, FingerprintLog::query()->count());
    }

    public function test_inactive_connection_and_ip_restriction_are_enforced(): void
    {
        $this->connection->update(['is_active' => false]);
        $this->punch($this->headers())->assertStatus(403);

        $this->connection->update(['is_active' => true, 'allowed_ips' => '203.0.113.10']);
        $this->punch($this->headers())->assertStatus(403);

        $this->connection->update(['allowed_ips' => '127.0.0.1']);
        $this->punch($this->headers())->assertOk();
    }

    public function test_invalid_payload_is_rejected_with_json_422(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/fingerprint-node/punches', [
            'punches' => [['pin' => '101', 'time' => 'kemarin']],
        ])->assertStatus(422);
    }

    public function test_admin_creates_connection_and_token_is_shown_once_and_stored_hashed(): void
    {
        FingerprintNodeConnection::query()->delete();
        $this->actingAs($this->superAdmin(), 'admin');

        $response = $this->put(route('admin.academic.student-attendance.settings.node.update', $this->school), [
            'username' => 'SD-Baru', 'password' => 'Password-Panjang-1', 'is_active' => 1,
        ]);
        $response->assertRedirect(route('admin.academic.student-attendance.settings.index'));

        $token = session('node_secret.token');
        $this->assertStringStartsWith('tbn_', $token);

        $connection = FingerprintNodeConnection::query()->firstOrFail();
        $this->assertSame('sd-baru', $connection->username);
        $this->assertNotSame('Password-Panjang-1', $connection->password);
        $this->assertSame(FingerprintNodeConnection::hashToken($token), $connection->token_hash);
        $this->assertDatabaseMissing('fingerprint_node_connections', ['token_hash' => $token]);

        $this->withHeaders($this->headers('Password-Panjang-1', $token, 'sd-baru'))
            ->postJson('/api/fingerprint-node/connect')->assertOk();
    }

    public function test_regenerating_token_invalidates_the_old_one_and_short_password_is_refused(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        $this->post(route('admin.academic.student-attendance.settings.node.token', $this->school))->assertRedirect();
        $this->punch($this->headers())->assertStatus(401);
        $new = session('node_secret.token');
        $this->punch($this->headers(null, $new))->assertOk();

        $this->put(route('admin.academic.student-attendance.settings.node.update', $this->school), [
            'username' => 'sd-satu', 'password' => 'pendek',
        ])->assertSessionHasErrors('password');
    }
}
