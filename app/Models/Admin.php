<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Akun login Pengurus Besar, KOMWIL (provinsi), dan KOMDA (kab/kota) — guard "admin", panel pengelola.<domain>. */
class Admin extends Authenticatable
{
    use HasRoles, Notifiable;

    protected string $guardName = 'admin';

    protected $fillable = ['name', 'username', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Role inti organisasi. Hanya Super Admin yang boleh menetapkan role ini ke akun mana pun
     * dan mengubah/menghapus akun yang memegangnya.
     */
    public const PROTECTED_ROLES = ['super-admin', 'pengurus-besar', 'komisaris-wilayah', 'komisaris-daerah'];

    /** Akun ini memegang salah satu role inti organisasi (termasuk Super Admin). */
    public function hasProtectedRole(): bool
    {
        return $this->roles()->whereIn('name', self::PROTECTED_ROLES)->exists();
    }

    /** Username selalu disimpan huruf kecil agar login tidak peka huruf besar/kecil. */
    protected function username(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn (?string $v) => $v === null ? null : \Illuminate\Support\Str::lower(trim($v)),
        );
    }

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'is_active'     => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
