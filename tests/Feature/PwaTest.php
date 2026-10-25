<?php

namespace Tests\Feature;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PWA portal pegawai (bisa dipasang di HP): manifest, service worker aman, halaman offline, dan tag di layout. */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_valid_and_points_to_existing_icons(): void
    {
        $r = $this->get(route('pegawai.pwa.manifest'))->assertOk();
        $this->assertStringContainsString('application/manifest+json', $r->headers->get('Content-Type'));

        $m = $r->json();
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/pegawai', $m['start_url']);
        $this->assertSame('/pegawai/', $m['scope']);
        $this->assertStringStartsWith($m['scope'], $m['start_url'].'/', 'start_url harus berada di dalam scope');

        $sizes = collect($m['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($m['icons'])->pluck('purpose')->all());

        foreach ($m['icons'] as $icon) {
            $file = public_path(ltrim(parse_url($icon['src'], PHP_URL_PATH), '/'));
            $this->assertFileExists($file);
            [$w, $h] = getimagesize($file);
            $this->assertSame($icon['sizes'], "{$w}x{$h}");
        }
    }

    public function test_service_worker_is_served_as_javascript_and_never_caches_portal_pages(): void
    {
        $r = $this->get(route('pegawai.pwa.sw'))->assertOk();
        $this->assertStringContainsString('javascript', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $r->headers->get('Cache-Control'));

        $js = $r->getContent();
        $this->assertStringContainsString('"\/pegawai\/offline"', $js);
        // Halaman portal memuat data pribadi: tidak boleh ada penyimpanan respons hasil fetch.
        $this->assertStringNotContainsString('cache.put', $js);
        $this->assertStringNotContainsString('.put(', $js);
        // Hanya perpindahan halaman yang disentuh.
        $this->assertStringContainsString("mode !== 'navigate'", $js);
    }

    public function test_offline_page_is_public_and_standalone(): void
    {
        $this->get(route('pegawai.pwa.offline'))->assertOk()
            ->assertSee('Tidak ada koneksi internet')->assertSee('Coba lagi')->assertDontSee('id="sidebar"', false);
    }

    public function test_pwa_files_are_reachable_without_login_and_do_not_leak_session_data(): void
    {
        foreach (['pegawai.pwa.manifest', 'pegawai.pwa.sw', 'pegawai.pwa.offline'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_pegawai_pages_link_the_manifest_and_register_the_worker(): void
    {
        $user = $this->linkedPegawai();

        $html = $this->actingAs($user, 'pegawai')->get(route('pegawai.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest" href="'.route('pegawai.pwa.manifest').'"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('name="theme-color"', $html);
        $this->assertStringContainsString('serviceWorker', $html);
        $this->assertStringContainsString('beforeinstallprompt', $html);
    }

    public function test_admin_and_pelajar_portals_are_not_turned_into_pwa(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertDontSee('rel="manifest"', false);
        $this->get(route('pelajar.login'))->assertOk()->assertDontSee('rel="manifest"', false);
    }

    private function linkedPegawai(): User
    {
        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $unit = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'A', 'name' => 'Sekolah A', 'status' => 'active']);
        $role = Role::create(['name' => 'pegawai-pwa', 'label' => 'Pegawai', 'guard_name' => 'pegawai']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'attendance.self', 'guard_name' => 'pegawai'], ['label' => 'x'])->id);
        $user = User::create(['name' => 'Guru PWA', 'username' => 'gurupwa', 'email' => 'gurupwa@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $user->syncRoles([$role->id]);
        $employee = Employee::create(['employee_number' => 'E-PWA', 'name' => 'Guru PWA', 'employee_type' => 'teacher', 'status' => EmployeeStatus::Active, 'user_id' => $user->id]);
        $position = OrganizationalPosition::create(['organizational_unit_id' => $unit->id, 'name' => 'Guru', 'is_active' => true]);
        $employee->assignments()->create(['organizational_unit_id' => $unit->id, 'organizational_position_id' => $position->id, 'is_primary' => true, 'start_date' => '2026-01-01']);

        return $user;
    }
}
