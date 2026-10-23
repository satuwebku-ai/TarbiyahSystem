<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Daftar akun demo untuk tombol "login satu klik" di halaman login.
 * Hanya akun yang sudah ada di database (dibuat oleh DemoSeeder).
 *
 * Tiap item: ['id', 'label', 'identifier', 'name', 'model'].
 */
class DemoAccounts
{
    public static function enabled(): bool
    {
        return (bool) config('domains.demo_login');
    }

    /** @return array<int, array{id:int,label:string,identifier:string,name:string,model:Model}> */
    public function for(string $guard): array
    {
        if (! self::enabled()) {
            return [];
        }

        try {
            return match ($guard) {
                'admin' => $this->admins(),
                'pegawai' => $this->pegawai(),
                'pelajar' => $this->pelajar(),
                default => [],
            };
        } catch (\Throwable) {
            return [];   // tabel belum dimigrasi dsb.
        }
    }

    public function find(string $guard, int $id): ?array
    {
        foreach ($this->for($guard) as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    /** Berurutan seperti hierarki pengelola: Super Admin -> Pengurus Besar -> Komwil -> Komda -> Kepala Sekolah. */
    private function admins(): array
    {
        $steps = [
            'Super Admin' => fn () => Admin::where('username', 'superadmin')->first()
                ?? Admin::where('username', config('domains.seed_admin.username'))->first(),
            'Pengurus Besar' => fn () => Admin::where('username', 'pb')->first(),
            'Komisaris Wilayah' => fn () => $this->firstAdminLike('komwil.%'),
            'Komisaris Daerah' => fn () => $this->firstAdminLike('komda.%'),
            'Kepala Sekolah' => fn () => $this->firstAdminLike('kepsek.%'),
        ];

        $list = [];
        foreach ($steps as $label => $resolver) {
            $admin = $resolver();
            if ($admin && $admin->is_active) {
                $list[] = $this->item($admin, $label, (string) $admin->username);
            }
        }

        return $list;
    }

    private function firstAdminLike(string $pattern): ?Admin
    {
        return Admin::where('username', 'like', $pattern)->where('is_active', true)->orderBy('id')->first();
    }

    /** Satu akun pegawai/guru yang sudah terhubung ke data pegawai. */
    private function pegawai(): array
    {
        $access = app(PegawaiPortalAccess::class);

        $candidates = User::query()->where('is_active', true)->whereHas('employee')->orderBy('id')->limit(10)->get();
        foreach ($candidates as $user) {
            if ($access->denialReason($user) === null) {
                return [$this->item($user, 'Pegawai / Guru', (string) $user->username)];
            }
        }

        return [];
    }

    /** Satu akun pelajar. */
    private function pelajar(): array
    {
        $account = StudentAccount::where('is_active', true)->where('nis', '1234567890')->first()
            ?? StudentAccount::where('is_active', true)->orderBy('id')->first();

        return $account ? [$this->item($account, 'Pelajar', (string) $account->nis)] : [];
    }

    private function item(Model $model, string $label, string $identifier): array
    {
        return [
            'id' => (int) $model->getKey(),
            'label' => $label,
            'identifier' => $identifier,
            'name' => (string) $model->name,
            'model' => $model,
        ];
    }
}
