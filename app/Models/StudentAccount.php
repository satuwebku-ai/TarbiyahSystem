<?php

namespace App\Models;

use App\Models\Concerns\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Akun login pelajar (guard "pelajar", portal pelajar.<domain>) — masuk memakai NIS. Data orangnya ada di Student. */
class StudentAccount extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $table = 'student_accounts';

    protected string $guardName = 'pelajar';

    protected $fillable = ['name', 'nis', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'      => 'hashed',
            'is_active'     => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'student_account_id');
    }
}
