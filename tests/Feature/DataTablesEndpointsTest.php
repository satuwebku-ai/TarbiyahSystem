<?php

namespace Tests\Feature;

use App\Console\Commands\PruneFingerprintPending;
use App\Models\Admin;
use App\Models\FingerprintDevice;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Yajra\DataTables\Facades\DataTables;

/**
 * Pengaman regresi untuk bug "Target class [datatables] does not exist" (cache package basi di server)
 * dan 500 pada endpoint tabel server-side.
 */
class DataTablesEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_datatables_service_is_registered(): void
    {
        $this->assertTrue(app()->bound('datatables'), 'Provider Yajra DataTables tidak terdaftar: jalankan php artisan package:discover.');
        $this->assertNotNull(DataTables::getFacadeRoot());
    }

    public function test_every_admin_datatable_endpoint_answers_200_json(): void
    {
        $role = Role::query()->create(['name' => 'super-admin', 'label' => 'Super Admin', 'guard_name' => 'admin', 'is_system' => true]);
        $admin = Admin::query()->create(['name' => 'SA', 'username' => 'sa-dt', 'email' => 'sa-dt@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);
        $this->actingAs($admin, 'admin');

        $names = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            $action = $route->getActionName();
            if (! $name || ! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true)
                || str_contains($route->uri(), '{') || ! str_contains($action, '@')) {
                continue;
            }
            [$class] = explode('@', $action);
            if (! class_exists($class)) {
                continue;
            }
            $file = (new \ReflectionClass($class))->getFileName();
            if ($file && str_contains((string) file_get_contents($file), 'Dt::wants')) {
                $names[] = $name;
            }
        }

        $this->assertNotEmpty($names, 'Tidak ada endpoint DataTables yang ditemukan.');

        foreach ($names as $name) {
            $this->getJson(route($name, ['draw' => 1, 'start' => 0, 'length' => 10]))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'], "Endpoint {$name} gagal.");
        }
    }

    public function test_prune_pending_removes_only_stale_unclaimed_devices(): void
    {
        $stale = FingerprintDevice::query()->create(['serial_number' => 'OLD001', 'name' => 'Lama', 'is_active' => false]);
        $stale->forceFill(['created_at' => now()->subDays(10)])->save();
        FingerprintDevice::query()->create(['serial_number' => 'NEW001', 'name' => 'Baru', 'is_active' => false]);

        $this->artisan('fingerprint:prune-pending', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseMissing('fingerprint_devices', ['serial_number' => 'OLD001']);
        $this->assertDatabaseHas('fingerprint_devices', ['serial_number' => 'NEW001']);
        $this->assertTrue(class_exists(PruneFingerprintPending::class));
    }
}
