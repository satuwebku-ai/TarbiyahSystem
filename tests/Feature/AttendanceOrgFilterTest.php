<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Admin;
use App\Models\AttendanceHoliday;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolLevel;
use App\Models\UserUnitAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filter bertingkat KOMWIL → KOMDA → Sekolah (Select2) pada semua halaman Absensi Pegawai,
 * beserta cakupan akses per peran: KOMWIL = wilayahnya, KOMDA = daerahnya, Kepala sekolah = sekolahnya.
 *
 *   Yayasan
 *   ├─ KW1 ── KD1 ── S1 (Guru Satu), S2 (Guru Dua)
 *   │      └─ KD2 ── S3 (Guru Tiga)
 *   └─ KW2 ── KD3 ── S4 (Guru Empat)
 */
class AttendanceOrgFilterTest extends TestCase
{
    use RefreshDatabase;

    private array $u = [];   // unit
    private array $s = [];   // school
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-21 05:00:00');

        $type = fn (string $slug) => OrganizationalUnitType::create(['name' => ucfirst($slug), 'slug' => $slug, 'is_active' => true]);
        $tYayasan = $type('yayasan');
        $tKomwil = $type('komwil');
        $tKomda = $type('komda');
        $tSekolah = $type('sekolah');
        $level = SchoolLevel::create(['name' => 'Sekolah Dasar', 'slug' => 'sd', 'sort_order' => 1, 'is_active' => true]);

        $make = fn (string $code, string $name, $t, ?OrganizationalUnit $parent = null) => OrganizationalUnit::create([
            'type_id' => $t->id, 'parent_id' => $parent?->id, 'code' => $code, 'name' => $name, 'status' => 'active',
        ]);
        $root = $make('Y', 'Yayasan', $tYayasan);
        $this->u['kw1'] = $make('KW1', 'Wilayah Satu', $tKomwil, $root);
        $this->u['kw2'] = $make('KW2', 'Wilayah Dua', $tKomwil, $root);
        $this->u['kd1'] = $make('KD1', 'Daerah Satu', $tKomda, $this->u['kw1']);
        $this->u['kd2'] = $make('KD2', 'Daerah Dua', $tKomda, $this->u['kw1']);
        $this->u['kd3'] = $make('KD3', 'Daerah Tiga', $tKomda, $this->u['kw2']);

        foreach (['s1' => ['kd1', 'Sekolah S1', 'Guru Satu'], 's2' => ['kd1', 'Sekolah S2', 'Guru Dua'],
                  's3' => ['kd2', 'Sekolah S3', 'Guru Tiga'], 's4' => ['kd3', 'Sekolah S4', 'Guru Empat']] as $key => [$parent, $name, $teacher]) {
            $this->u[$key] = $make(strtoupper($key), $name, $tSekolah, $this->u[$parent]);
            $this->s[$key] = School::create(['organizational_unit_id' => $this->u[$key]->id, 'school_level_id' => $level->id, 'status' => 'active']);
            $this->employee($this->u[$key], $teacher);
        }
    }

    // ---- rekap harian & bulanan ---------------------------------------

    public function test_super_admin_narrows_recap_by_komwil_komda_and_school(): void
    {
        $this->actingAs($this->admin(['attendance.view'], null, super: true), 'admin');
        $day = ['date' => '2026-10-20'];

        $this->get(route('admin.attendance.daily', $day))->assertOk()
            ->assertSee('Guru Satu')->assertSee('Guru Dua')->assertSee('Guru Tiga')->assertSee('Guru Empat');

        $this->assertNames(['Guru Satu', 'Guru Dua', 'Guru Tiga'], ['Guru Empat'],
            $this->get(route('admin.attendance.daily', $day + ['komwil_id' => $this->u['kw1']->id])));
        $this->assertNames(['Guru Satu', 'Guru Dua'], ['Guru Tiga', 'Guru Empat'],
            $this->get(route('admin.attendance.daily', $day + ['komwil_id' => $this->u['kw1']->id, 'komda_id' => $this->u['kd1']->id])));
        $this->assertNames(['Guru Dua'], ['Guru Satu', 'Guru Tiga', 'Guru Empat'],
            $this->get(route('admin.attendance.daily', $day + ['school_id' => $this->s['s2']->id])));
        $this->assertNames(['Guru Empat'], ['Guru Satu'],
            $this->get(route('admin.attendance.monthly', ['month' => '2026-10', 'komwil_id' => $this->u['kw2']->id])));
    }

    public function test_conflicting_filters_never_widen_the_result(): void
    {
        $this->actingAs($this->admin(['attendance.view'], null, super: true), 'admin');

        // KOMDA 3 bukan bagian dari KOMWIL 1 → hasil kosong, bukan gabungan
        $this->assertNames([], ['Guru Satu', 'Guru Tiga', 'Guru Empat'],
            $this->get(route('admin.attendance.daily', ['date' => '2026-10-20', 'komwil_id' => $this->u['kw1']->id, 'komda_id' => $this->u['kd3']->id])));
        // sekolah di luar KOMDA terpilih
        $this->assertNames([], ['Guru Satu', 'Guru Dua'],
            $this->get(route('admin.attendance.daily', ['date' => '2026-10-20', 'komda_id' => $this->u['kd2']->id, 'school_id' => $this->s['s1']->id])));
    }

    public function test_komwil_komda_and_headmaster_only_see_their_own_scope(): void
    {
        $komwil = $this->admin(['attendance.view'], $this->u['kw1']);
        $komda = $this->admin(['attendance.view'], $this->u['kd1']);
        $kepsek = $this->admin(['attendance.view'], $this->u['s1']);
        $day = ['date' => '2026-10-20'];

        $this->actingAs($komwil, 'admin');
        $this->assertNames(['Guru Satu', 'Guru Dua', 'Guru Tiga'], ['Guru Empat'], $this->get(route('admin.attendance.daily', $day)));
        $this->assertNames([], ['Guru Empat'], $this->get(route('admin.attendance.daily', $day + ['komwil_id' => $this->u['kw2']->id])));
        $this->get(route('admin.attendance.daily', $day + ['school_id' => $this->s['s4']->id]))->assertForbidden();

        $this->actingAs($komda, 'admin');
        $this->assertNames(['Guru Satu', 'Guru Dua'], ['Guru Tiga', 'Guru Empat'], $this->get(route('admin.attendance.daily', $day)));
        $this->get(route('admin.attendance.daily', $day + ['school_id' => $this->s['s3']->id]))->assertForbidden();

        $this->actingAs($kepsek, 'admin');
        $this->assertNames(['Guru Satu'], ['Guru Dua', 'Guru Tiga', 'Guru Empat'], $this->get(route('admin.attendance.monthly', ['month' => '2026-10'])));
        $this->get(route('admin.attendance.daily', $day + ['school_id' => $this->s['s2']->id]))->assertForbidden();
    }

    public function test_filter_form_adapts_to_the_scope_of_the_admin(): void
    {
        $day = ['date' => '2026-10-20'];

        // Pengurus Besar: tiga tingkat, semuanya Select2 (aset dimuat)
        $this->actingAs($this->admin(['attendance.view'], null, super: true), 'admin');
        $html = $this->get(route('admin.attendance.daily', $day))->assertOk()
            ->assertSee('id="komwil_id"', false)->assertSee('id="komda_id"', false)->assertSee('id="school_id"', false)
            ->assertSee('select2.full.min.js', false)->assertSee('NO_UPSTREAM', false)->getContent();
        $this->assertStringContainsString('data-komwil-id="'.$this->u['kw1']->id.'"', $html);
        $this->assertStringContainsString('data-komda-id="'.$this->u['kd1']->id.'"', $html);

        // KOMWIL: tidak perlu memilih KOMWIL, tapi KOMDA dan sekolah tersedia
        $this->actingAs($this->admin(['attendance.view'], $this->u['kw1']), 'admin');
        $this->get(route('admin.attendance.daily', $day))->assertOk()
            ->assertDontSee('id="komwil_id"', false)->assertSee('id="komda_id"', false)->assertSee('id="school_id"', false);

        // KOMDA: hanya dropdown sekolah (daerahnya sendiri)
        $this->actingAs($this->admin(['attendance.view'], $this->u['kd1']), 'admin');
        $html = $this->get(route('admin.attendance.daily', $day))->assertOk()
            ->assertDontSee('id="komwil_id"', false)->assertDontSee('id="komda_id"', false)->assertSee('id="school_id"', false)->getContent();
        $this->assertStringNotContainsString('Sekolah S3', $html);
        $this->assertStringNotContainsString('Sekolah S4', $html);

        // Kepala sekolah: sekolah tunggal dikirim sebagai input tersembunyi
        $this->actingAs($this->admin(['attendance.view'], $this->u['s1']), 'admin');
        $this->get(route('admin.attendance.daily', $day))->assertOk()
            ->assertSee('type="hidden" id="school_id" name="school_id" value="'.$this->s['s1']->id.'"', false)
            ->assertDontSee('id="komda_id"', false);
    }

    // ---- koreksi ------------------------------------------------------

    public function test_corrections_can_be_filtered_by_school_and_stay_in_scope(): void
    {
        foreach (['s1' => 'Guru Satu', 's3' => 'Guru Tiga', 's4' => 'Guru Empat'] as $key => $name) {
            \App\Models\AttendanceCorrection::create([
                'employee_id' => Employee::where('name', $name)->value('id'),
                'attendance_date' => '2026-10-19', 'requested_check_in' => '07:00', 'requested_check_out' => '15:00',
                'reason' => "Alasan {$key}", 'status' => 'pending',
            ]);
        }

        $this->actingAs($this->admin(['attendance.corrections.review'], $this->u['kw1']), 'admin');
        $this->dt('admin.attendance.corrections.index')->assertOk()
            ->assertSee('Guru Satu')->assertSee('Guru Tiga')->assertDontSee('Guru Empat');
        $this->dt('admin.attendance.corrections.index', ['school_id' => $this->s['s3']->id])->assertOk()
            ->assertSee('Guru Tiga')->assertDontSee('Guru Satu');
        // tab status digerakkan JS dan memakai filter form yang sama
        $this->get(route('admin.attendance.corrections.index', ['komda_id' => $this->u['kd1']->id]))
            ->assertOk()->assertSee('data-dt-tab="status"', false);
        $this->dt('admin.attendance.corrections.index', ['school_id' => $this->s['s4']->id])->assertForbidden();
    }

    // ---- izin / sakit / cuti -------------------------------------------

    public function test_entries_employee_picker_carries_org_attributes_and_history_is_filtered(): void
    {
        foreach (['s1' => 'Guru Satu', 's3' => 'Guru Tiga'] as $key => $name) {
            AttendanceRecord::create([
                'employee_id' => Employee::where('name', $name)->value('id'), 'organizational_unit_id' => $this->u[$key]->id,
                'attendance_date' => '2026-10-19', 'status' => AttendanceStatus::Sick, 'source' => 'manual', 'notes' => "Catatan {$key}",
            ]);
        }

        $this->actingAs($this->admin(['attendance.records.manage'], $this->u['kw1']), 'admin');
        $html = $this->get(route('admin.attendance.entries.index'))->assertOk()
            ->assertSee('id="employee_id"', false)->getContent();
        $this->dt('admin.attendance.entries.index')->assertOk()->assertSee('Catatan s1')->assertSee('Catatan s3');

        $satu = Employee::where('name', 'Guru Satu')->first();
        $this->assertMatchesRegularExpression(
            '/<option value="'.$satu->id.'" data-komwil-id="'.$this->u['kw1']->id.'" data-komda-id="'.$this->u['kd1']->id.'" data-school-id="'.$this->s['s1']->id.'"/',
            $html,
        );
        $this->assertStringNotContainsString('Guru Empat', $html);

        $this->dt('admin.attendance.entries.index', ['komda_id' => $this->u['kd2']->id])->assertOk()
            ->assertSee('Catatan s3')->assertDontSee('Catatan s1');
    }

    // ---- hari libur & kebijakan ----------------------------------------

    public function test_holidays_and_policies_lists_follow_the_selected_unit_chain(): void
    {
        foreach (['Libur Umum' => null, 'Libur KW1' => 'kw1', 'Libur KD1' => 'kd1', 'Libur S1' => 's1', 'Libur S2' => 's2', 'Libur KD2' => 'kd2'] as $name => $unit) {
            AttendanceHoliday::create(['holiday_date' => '2026-10-'.sprintf('%02d', ++$this->seq % 28 + 1), 'name' => $name, 'organizational_unit_id' => $unit ? $this->u[$unit]->id : null]);
        }
        foreach (['Kebijakan Umum' => null, 'Kebijakan KD1' => 'kd1', 'Kebijakan S1' => 's1', 'Kebijakan KD2' => 'kd2'] as $name => $unit) {
            AttendancePolicy::create([
                'name' => $name, 'work_days' => [1, 2, 3, 4, 5], 'work_start' => '07:00', 'work_end' => '15:00', 'late_tolerance_minutes' => 15,
                'is_active' => $unit !== null, 'organizational_unit_id' => $unit ? $this->u[$unit]->id : null,
            ]);
        }

        $this->actingAs($this->admin(['attendance.holidays.manage', 'attendance.policies.manage'], null, super: true), 'admin');

        $this->dt('admin.attendance.holidays.index', ['year' => 2026, 'school_id' => $this->s['s1']->id])->assertOk()
            ->assertSee('Libur Umum')->assertSee('Libur KW1')->assertSee('Libur KD1')->assertSee('Libur S1')
            ->assertDontSee('Libur S2')->assertDontSee('Libur KD2');
        $this->dt('admin.attendance.holidays.index', ['year' => 2026, 'komda_id' => $this->u['kd1']->id])
            ->assertSee('Libur S1')->assertSee('Libur S2')->assertDontSee('Libur KD2');

        $this->dt('admin.attendance.policies.index', ['school_id' => $this->s['s1']->id])->assertOk()
            ->assertSee('Kebijakan Umum')->assertSee('Kebijakan KD1')->assertSee('Kebijakan S1')->assertDontSee('Kebijakan KD2');

        // "Berlaku untuk" memakai Select2 pohon yang bisa dicari
        $this->get(route('admin.attendance.holidays.index'))->assertSee('js-unit-select', false);
        $this->get(route('admin.attendance.policies.create'))->assertOk()->assertSee('js-unit-select', false);
    }

    // ---- AJAX ---------------------------------------------------------

    public function test_every_attendance_table_returns_only_its_fragment_for_ajax_requests(): void
    {
        $this->actingAs($this->admin([
            'attendance.view', 'attendance.corrections.review', 'attendance.records.manage',
            'attendance.policies.manage', 'attendance.holidays.manage',
        ], null, super: true), 'admin');
        AttendanceHoliday::create(['holiday_date' => '2026-10-10', 'name' => 'Libur Fragmen', 'organizational_unit_id' => $this->u['s1']->id]);
        AttendanceHoliday::create(['holiday_date' => '2026-10-11', 'name' => 'Libur Lain', 'organizational_unit_id' => $this->u['s3']->id]);

        // Rekap harian & bulanan: halaman memuat ulang fragmen tabel lewat AJAX.
        foreach ([['admin.attendance.daily', ['date' => '2026-10-20']], ['admin.attendance.monthly', ['month' => '2026-10']]] as [$route, $query]) {
            $query += ['school_id' => $this->s['s1']->id];

            $this->get(route($route, $query))->assertOk()
                ->assertSee('data-ajax-region', false)->assertSee('data-ajax-filter', false)
                ->assertSee('<html', false)->assertSee('is-loading', false)->assertSee('XMLHttpRequest', false);

            $ajax = $this->get(route($route, $query), ['X-Requested-With' => 'XMLHttpRequest']);
            $ajax->assertOk()->assertDontSee('<html', false)->assertDontSee('id="sidebar"', false);
            $this->assertStringContainsString('<table', $ajax->getContent(), $route);
        }

        // Koreksi, izin/sakit/cuti, hari libur, kebijakan: tabel DataTables, isinya JSON (bukan halaman penuh).
        foreach (['admin.attendance.corrections.index', 'admin.attendance.entries.index', 'admin.attendance.holidays.index', 'admin.attendance.policies.index'] as $route) {
            $query = ['school_id' => $this->s['s1']->id] + ($route === 'admin.attendance.holidays.index' ? ['year' => 2026] : []);

            $this->get(route($route, $query))->assertOk()->assertSee('<html', false);
            $this->dt($route, $query)->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
                ->assertDontSee('<html', false);
        }

        // fragmen tetap tersaring oleh filter bertingkat
        $daily = $this->get(route('admin.attendance.daily', ['date' => '2026-10-20', 'school_id' => $this->s['s2']->id]), ['X-Requested-With' => 'XMLHttpRequest']);
        $daily->assertSee('Guru Dua')->assertDontSee('Guru Satu')->assertDontSee('Guru Empat');
        $holidays = $this->dt('admin.attendance.holidays.index', ['year' => 2026, 'school_id' => $this->s['s1']->id]);
        $holidays->assertSee('Libur Fragmen')->assertDontSee('Libur Lain');
    }

    public function test_ajax_requests_keep_the_scope_checks(): void
    {
        $this->actingAs($this->admin(['attendance.view'], $this->u['kd1']), 'admin');
        $this->get(route('admin.attendance.daily', ['school_id' => $this->s['s3']->id]), ['X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();
    }

    // ---- helper --------------------------------------------------------

    /** Tabel DataTables (server-side): isi baris dikirim sebagai JSON, bukan HTML halaman. */
    private function dt(string $route, array $query = [])
    {
        return $this->getJson(route($route, $query + ['draw' => 1, 'start' => 0, 'length' => 100]));
    }

    private function assertNames(array $seen, array $unseen, $response): void
    {
        $response->assertOk();
        foreach ($seen as $name) {
            $response->assertSee($name);
        }
        foreach ($unseen as $name) {
            $response->assertDontSee($name);
        }
    }

    private function employee(OrganizationalUnit $unit, string $name): Employee
    {
        $n = ++$this->seq;
        $employee = Employee::create(['employee_number' => "E-$n", 'name' => $name, 'employee_type' => 'teacher', 'status' => EmployeeStatus::Active]);
        $position = OrganizationalPosition::firstOrCreate(['organizational_unit_id' => $unit->id, 'name' => 'Guru'], ['is_active' => true]);
        $employee->assignments()->create(['organizational_unit_id' => $unit->id, 'organizational_position_id' => $position->id, 'is_primary' => true, 'start_date' => '2026-01-01']);

        return $employee;
    }

    private function admin(array $permissions, ?OrganizationalUnit $scope = null, bool $super = false): Admin
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => $super ? 'super-admin' : "role-$n", 'label' => "Role $n", 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }
        $admin = Admin::create(['name' => "Admin $n", 'username' => "admin$n", 'email' => "admin$n@example.test", 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);
        if ($scope) {
            UserUnitAccess::create(['organizational_unit_id' => $scope->id, 'model_type' => Admin::class, 'model_id' => $admin->id, 'created_at' => now()]);
        }

        return $admin;
    }
}
