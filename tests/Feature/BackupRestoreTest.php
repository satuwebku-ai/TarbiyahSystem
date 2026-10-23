<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use App\Services\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Fase 11 — pengujian backup dan pemulihan database (berkas sementara, SQLite). */
class BackupRestoreTest extends TestCase
{
    private string $dir;
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/backup-test-'.uniqid();
        mkdir($this->dir);
        $this->dbFile = $this->dir.'/app.sqlite';
        touch($this->dbFile);

        // Database berkas sungguhan (bukan :memory:) agar sama seperti lingkungan nyata.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->dbFile]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_backup_then_restore_brings_back_the_exact_data(): void
    {
        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $unit = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'A', 'name' => 'Sekolah Ünïcode — A', 'status' => 'active']);
        $admin = Admin::create(['name' => 'Budi', 'username' => 'budi', 'email' => 'budi@example.test', 'password' => 'Secure-Test-Password-2026!', 'is_active' => true]);
        AdminAuditLog::record('test.event', $admin, $unit, ['catatan' => 'sebelum']);
        $hash = $admin->fresh()->password;

        $file = $this->dir.'/full.jsonl.gz';
        $counts = app(DatabaseBackupService::class)->backup($file);
        $this->assertFileExists($file);
        $this->assertSame(1, $counts['admins']);

        // Kerusakan/kesalahan setelah backup: data diubah, dihapus, dan ditambah.
        $unit->update(['name' => 'RUSAK']);
        $admin->delete();
        OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'Z', 'name' => 'Baru', 'status' => 'active']);
        DB::table('admin_audit_logs')->delete();

        app(DatabaseBackupService::class)->restore($file);

        $this->assertSame('Sekolah Ünïcode — A', OrganizationalUnit::whereKey($unit->id)->value('name'));
        $this->assertSame(1, OrganizationalUnit::count(), 'Data yang dibuat setelah backup harus hilang.');
        $restored = Admin::findOrFail($admin->id);
        $this->assertSame($hash, $restored->password, 'Hash kata sandi harus kembali persis, tidak di-hash ulang.');
        $this->assertSame(['catatan' => 'sebelum'], AdminAuditLog::firstOrFail()->details);

        // ID berikutnya melanjutkan urutan, tidak bentrok dengan data yang dipulihkan.
        $next = OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'N', 'name' => 'Setelah pulih', 'status' => 'active']);
        $this->assertGreaterThan($unit->id, $next->id);
    }

    public function test_restore_is_all_or_nothing_when_the_file_is_broken(): void
    {
        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        OrganizationalUnit::create(['type_id' => $type->id, 'code' => 'A', 'name' => 'Asli', 'status' => 'active']);

        $good = $this->dir.'/good.jsonl.gz';
        app(DatabaseBackupService::class)->backup($good);

        // Berkas rusak: metadata benar, tetapi baris data terpotong.
        $broken = $this->dir.'/broken.jsonl.gz';
        $lines = explode("\n", gzdecode(file_get_contents($good)));
        $lines[count($lines) - 2] = '{"t":"organizational_units","r":{"id":99,';
        file_put_contents($broken, gzencode(implode("\n", $lines)));

        OrganizationalUnit::query()->update(['name' => 'Diubah setelah backup']);

        try {
            app(DatabaseBackupService::class)->restore($broken);
            $this->fail('Pemulihan dari berkas rusak seharusnya gagal.');
        } catch (\Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertSame('Diubah setelah backup', OrganizationalUnit::firstOrFail()->name, 'Data lama harus tetap utuh bila pemulihan gagal.');
        $this->assertSame(1, OrganizationalUnit::count());
    }

    public function test_restore_is_refused_when_the_schema_version_differs(): void
    {
        $file = $this->dir.'/old.jsonl.gz';
        app(DatabaseBackupService::class)->backup($file);

        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_future_change', 'batch' => 99]);

        $this->expectExceptionMessage('Versi skema berbeda');
        app(DatabaseBackupService::class)->restore($file);
    }

    public function test_restore_rejects_files_that_are_not_backups(): void
    {
        $junk = $this->dir.'/junk.jsonl.gz';
        file_put_contents($junk, gzencode("bukan json\n"));

        $this->expectExceptionMessage('rusak');
        app(DatabaseBackupService::class)->readMeta($junk);
    }

    public function test_commands_back_up_and_restore_with_confirmation(): void
    {
        $type = OrganizationalUnitType::create(['name' => 'Unit', 'slug' => 'unit', 'is_active' => true]);
        $file = $this->dir.'/cmd.jsonl.gz';

        $this->artisan('db:backup', ['--path' => $file])->assertSuccessful();
        $this->assertFileExists($file);

        OrganizationalUnitType::query()->delete();
        $this->assertSame(0, OrganizationalUnitType::count());

        // Menolak konfirmasi: tidak ada yang berubah.
        $this->artisan('db:restore', ['file' => $file])->expectsConfirmation('Lanjutkan pemulihan?', 'no')->assertFailed();
        $this->assertSame(0, OrganizationalUnitType::count());

        $this->artisan('db:restore', ['file' => $file])->expectsConfirmation('Lanjutkan pemulihan?', 'yes')->assertSuccessful();
        $this->assertSame($type->id, OrganizationalUnitType::firstOrFail()->id);

        $this->artisan('db:restore', ['file' => $this->dir.'/tidak-ada.gz', '--force' => true])->assertFailed();
    }

    public function test_session_and_cache_tables_are_not_part_of_a_backup(): void
    {
        $tables = app(DatabaseBackupService::class)->tables();

        foreach (['sessions', 'cache', 'cache_locks', 'migrations'] as $excluded) {
            $this->assertNotContains($excluded, $tables);
        }
        $this->assertContains('admins', $tables);
        $this->assertContains('admin_audit_logs', $tables);
    }
}
