<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Kredensial koneksi aplikasi Node fingerprint milik satu sekolah.
 * Aplikasi Node mengirim: Basic auth (username:password) + header X-Node-Token.
 * Password disimpan sebagai hash, token hanya sebagai SHA-256 (token asli ditampilkan sekali saat dibuat).
 */
class FingerprintNodeConnection extends Model
{
    public const TOKEN_PREFIX = 'tbn_';

    public const ONLINE_WITHIN_MINUTES = 5;

    private static ?string $dummyHash = null;

    protected $fillable = ['school_id', 'username', 'is_active', 'allowed_ips'];

    protected $hidden = ['password', 'token_hash'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'token_rotated_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_log_at' => 'datetime',
            'total_logs' => 'integer',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Isi atribut token baru (hash + awalan) tanpa menyimpan; kembalikan token asli. */
    public function assignToken(): string
    {
        $token = self::TOKEN_PREFIX.Str::random(40);
        $this->forceFill([
            'token_hash' => self::hashToken($token),
            'token_prefix' => substr($token, 0, 8),
            'token_rotated_at' => now(),
        ]);

        return $token;
    }

    /** Buat token baru, simpan hash-nya, dan kembalikan token asli (satu-satunya kesempatan melihatnya). */
    public function rotateToken(): string
    {
        $token = $this->assignToken();
        $this->save();

        return $token;
    }

    /** Cocokkan ketiga kredensial. Mengembalikan null bila salah satunya tidak cocok. */
    public static function authenticate(string $username, string $password, string $token): ?self
    {
        $connection = static::query()->where('token_hash', self::hashToken($token))->first();

        if (! $connection) {
            // Samakan waktu respons agar token yang salah tidak dapat dibedakan dari password yang salah.
            self::$dummyHash ??= Hash::make(Str::random(16));
            Hash::check($password, self::$dummyHash);

            return null;
        }

        $passwordOk = Hash::check($password, $connection->password);
        $usernameOk = hash_equals(strtolower($connection->username), strtolower($username));

        return $passwordOk && $usernameOk ? $connection : null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subMinutes(self::ONLINE_WITHIN_MINUTES));
    }

    public function hasIpRestriction(): bool
    {
        return array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))) !== [];
    }

    /** Kosong = semua IP diterima. */
    public function allowsIp(?string $ip): bool
    {
        $allowed = array_values(array_filter(array_map('trim', explode(',', (string) $this->allowed_ips))));
        if ($allowed === []) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $allowed);
    }
}
