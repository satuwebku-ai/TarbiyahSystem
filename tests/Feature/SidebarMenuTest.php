<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_employee_menu_item_is_active_at_a_time(): void
    {
        $role = Role::create(['name' => 'super-admin', 'label' => 'Super Admin', 'guard_name' => 'admin']);
        $admin = Admin::create(['name' => 'SA', 'username' => 'sa', 'email' => 'sa@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        $admin->syncRoles([$role->id]);
        $this->actingAs($admin, 'admin');

        $state = fn (string $html, string $label) => preg_match('/data-tip="'.preg_quote($label, '/').'"\s+class="nav-item-link (active|text-white-50)/', $html, $m) ? $m[1] : null;

        $html = $this->get(route('admin.employees.types.index'))->assertOk()->getContent();
        $this->assertSame('active', $state($html, 'Jenis Pegawai'));
        $this->assertSame('text-white-50', $state($html, 'Data Pegawai'));

        foreach ([route('admin.employees.index'), route('admin.employees.create')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame('active', $state($html, 'Data Pegawai'), $url);
            $this->assertSame('text-white-50', $state($html, 'Jenis Pegawai'), $url);
        }
    }
}
