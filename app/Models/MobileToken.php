<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Token akses aplikasi mobile. Teks token hanya diketahui perangkat (dikirim sekali saat login);
 * di database tersimpan hash-nya, jadi kebocoran tabel tidak membocorkan sesi.
 */
class MobileToken extends Model
{
    protected $fillable = ['tokenable_type', 'tokenable_id', 'name', 'token_hash', 'last_used_at', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /** 'pegawai' untuk User, 'pelajar' untuk StudentAccount; null untuk akun lain (mis. admin tidak boleh). */
    public static function roleOf(?Model $account): ?string
    {
        return match (true) {
            $account instanceof User => 'pegawai',
            $account instanceof StudentAccount => 'pelajar',
            default => null,
        };
    }

    /** @return array{0: self, 1: string} [model, teks token] */
    public static function issue(Model $account, string $name = 'mobile'): array
    {
        $plain = Str::random(60);

        // Batasi perangkat aktif: sisakan (maks - 1) token terbaru, sisanya dicabut, lalu token baru dibuat.
        $max = max(1, (int) config('mobile.max_tokens_per_account', 5));
        $stale = static::query()
            ->where('tokenable_type', $account->getMorphClass())
            ->where('tokenable_id', $account->getKey())
            ->orderByDesc('id')
            ->pluck('id')
            ->slice($max - 1);
        if ($stale->isNotEmpty()) {
            static::query()->whereIn('id', $stale->all())->delete();
        }

        $token = static::create([
            'tokenable_type' => $account->getMorphClass(),
            'tokenable_id' => $account->getKey(),
            'name' => mb_substr($name, 0, 100),
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(max(1, (int) config('mobile.token_ttl_days', 30))),
        ]);

        return [$token, $plain];
    }

    public static function findByPlain(string $plain): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $plain))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }
}
