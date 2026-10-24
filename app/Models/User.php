<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/** Akun login pegawai dan guru (guard "pegawai", portal pegawai.<domain>). Data orangnya ada di Employee. */
class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected string $guardName = 'pegawai';

    protected $fillable = ['name', 'username', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /** Username selalu disimpan huruf kecil agar login tidak peka huruf besar/kecil. */
    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v === null ? null : Str::lower(trim($v)),
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

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }
}
