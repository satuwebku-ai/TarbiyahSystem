<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris pengaturan aplikasi (key => value). Akses lewat App\Services\AppSettings,
 * bukan langsung: service itu yang mengurus enkripsi nilai rahasia, cache, dan penerapan ke config().
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'is_secret'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['is_secret' => 'boolean'];
    }
}
