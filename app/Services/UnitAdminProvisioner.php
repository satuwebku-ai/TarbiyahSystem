<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\AdminSecuritySetting;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UserUnitAccess;
use App\Rules\AdminPasswordPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Membuat satu akun pengelola (admin) untuk tiap unit di struktur organisasi: peran mengikuti jenis unit dan
 * cakupan akunnya dikunci ke unit itu (beserta turunannya). Idempoten: unit yang sudah punya akun berperan sama dilewati.
 *
 * Kata sandi sementara dibuat acak per akun dan HANYA dikembalikan sekali dari provision(); tidak disimpan dalam bentuk asli.
 */
class UnitAdminProvisioner
{
    /** Jenis unit (slug) => nama peran admin. Jenis lain (mis. majelis) tidak punya peran bawaan sehingga tidak dibuatkan akun. */
    public const ROLE_BY_TYPE = [
        'pengurus-besar' => 'pengurus-besar',
        'komwil' => 'komisaris-wilayah',
        'komda' => 'komisaris-daerah',
        'sekolah' => 'kepala-sekolah',
    ];

    public const STATUS_CREATE = 'create';

    public const STATUS_EXISTS = 'exists';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ROLE_MISSING = 'role_missing';

    public const DEFAULT_EMAIL_DOMAIN = 'admin.invalid';

    /**
     * @param  list<string>|null  $types  slug jenis unit; null = semua yang punya peran bawaan
     * @return list<array{unit: OrganizationalUnit, type: string, role: ?Role, status: string, username: ?string, email: ?string, name: string}>
     */
    public function plan(?array $types = null, ?string $emailDomain = null): array
    {
        $types = array_values(array_intersect($types ?: array_keys(self::ROLE_BY_TYPE), array_keys(self::ROLE_BY_TYPE)));
        $domain = Str::lower(trim((string) $emailDomain)) ?: self::DEFAULT_EMAIL_DOMAIN;

        $roles = Role::query()->where('guard_name', 'admin')->whereIn('name', array_values(self::ROLE_BY_TYPE))->get()->keyBy('name');
        $covered = $this->coveredUnits($roles->keys()->all());
        $takenUsernames = Admin::query()->pluck('username')->mapWithKeys(fn ($u) => [Str::lower((string) $u) => true])->all();
        $takenEmails = Admin::query()->pluck('email')->mapWithKeys(fn ($e) => [Str::lower((string) $e) => true])->all();

        $units = OrganizationalUnit::query()
            ->whereHas('type', fn ($q) => $q->whereIn('slug', $types))
            ->with('type:id,slug,name')->orderBy('id')->get();

        $rows = [];
        foreach ($units as $unit) {
            $slug = (string) $unit->type->slug;
            $role = $roles->get(self::ROLE_BY_TYPE[$slug]);
            $row = ['unit' => $unit, 'type' => $slug, 'role' => $role, 'username' => null, 'email' => null,
                'name' => Str::limit('Admin '.$unit->name, 100, '')];

            $row['status'] = match (true) {
                $role === null => self::STATUS_ROLE_MISSING,
                $unit->status !== 'active' => self::STATUS_INACTIVE,
                in_array($unit->id, $covered[$role->name] ?? [], true) => self::STATUS_EXISTS,
                default => self::STATUS_CREATE,
            };

            if ($row['status'] === self::STATUS_CREATE) {
                $row['username'] = $this->uniqueUsername($unit, $slug, $takenUsernames);
                $row['email'] = $this->uniqueEmail($row['username'], $domain, $takenEmails);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $plan  hasil plan()
     * @return list<array{unit: OrganizationalUnit, role: Role, name: string, username: string, email: string, password: string, active: bool}>
     */
    public function provision(array $plan, ?Authenticatable $actor, bool $active = true): array
    {
        $created = [];
        foreach ($plan as $row) {
            if ($row['status'] !== self::STATUS_CREATE) {
                continue;
            }
            $password = $this->password();

            DB::transaction(function () use ($row, $password, $actor, $active) {
                $admin = Admin::create([
                    'name' => $row['name'], 'username' => $row['username'], 'email' => $row['email'],
                    'password' => $password, 'is_active' => $active,
                ]);
                $admin->syncRoles([$row['role']->id]);
                UserUnitAccess::create([
                    'organizational_unit_id' => $row['unit']->id, 'model_type' => Admin::class,
                    'model_id' => $admin->id, 'created_at' => now(),
                ]);
                AdminAuditLog::record('admin.created', $actor, $admin, [
                    'is_active' => $active, 'source' => 'unit-provisioning',
                    'organizational_unit_id' => $row['unit']->id, 'role' => $row['role']->name,
                ]);
                AdminAuditLog::record('organization.access.updated', $actor, $admin, ['organizational_unit_ids' => [$row['unit']->id]]);
            });

            $created[] = [
                'unit' => $row['unit'], 'role' => $row['role'], 'name' => $row['name'], 'username' => $row['username'],
                'email' => $row['email'], 'password' => $password, 'active' => $active,
            ];
        }

        return $created;
    }

    /** @param list<string> $roleNames @return array<string, list<int>> nama peran => id unit yang sudah punya admin berperan itu */
    private function coveredUnits(array $roleNames): array
    {
        $covered = [];
        foreach ($roleNames as $name) {
            $adminIds = Admin::query()->whereHas('roles', fn ($q) => $q->where('roles.name', $name))->pluck('id');
            $covered[$name] = UserUnitAccess::query()->where('model_type', Admin::class)->whereIn('model_id', $adminIds)
                ->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->all();
        }

        return $covered;
    }

    /** @param array<string, true> $taken */
    private function uniqueUsername(OrganizationalUnit $unit, string $slug, array &$taken): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9._-]+/', '-', Str::lower((string) $unit->code)), '-._');
        if (strlen($base) < 3) {
            $base = trim($slug.'-'.Str::slug((string) $unit->name), '-') ?: "{$slug}-{$unit->id}";
        }
        $base = substr($base, 0, 44);

        $username = $base;
        for ($i = 2; isset($taken[$username]); $i++) {
            $username = $base.'-'.$i;
        }
        $taken[$username] = true;

        return $username;
    }

    /** @param array<string, true> $taken */
    private function uniqueEmail(string $username, string $domain, array &$taken): string
    {
        $email = "{$username}@{$domain}";
        for ($i = 2; isset($taken[$email]); $i++) {
            $email = "{$username}-{$i}@{$domain}";
        }
        $taken[$email] = true;

        return $email;
    }

    private function password(): string
    {
        $settings = AdminSecuritySetting::current();
        $length = max(16, (int) $settings->password_min_length + 4);

        for ($i = 0; $i < 50; $i++) {
            $candidate = Str::password($length, true, true, (bool) $settings->password_require_symbols, false);
            if (Validator::make(['p' => $candidate], ['p' => [AdminPasswordPolicy::rule()]])->passes()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Gagal membuat kata sandi yang memenuhi kebijakan keamanan.');
    }
}
