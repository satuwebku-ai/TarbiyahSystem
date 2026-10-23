<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pengaturan aplikasi yang bisa diubah dari UI (tabel app_settings), ditimpakan ke config() saat boot.
 * Definisi kunci yang boleh diubah ada di config/app_settings.php; kunci lain tetap di .env.
 *
 * Aman dipanggil saat tabel belum ada (instalasi baru, migrate) atau database belum bisa dijangkau:
 * apply() diam-diam tidak melakukan apa pun, dan nilai .env/config bawaan tetap berlaku.
 */
class AppSettings
{
    private const CACHE_KEY = 'app_settings.rows';

    /** Nilai tersimpan (rahasia sudah didekripsi), hanya di memori proses ini. */
    private ?array $memo = null;

    /** @return array<string, array> grup => definisi grup */
    public function groups(): array
    {
        return (array) config('app_settings.groups', []);
    }

    public function group(string $group): ?array
    {
        return $this->groups()[$group] ?? null;
    }

    /** @return array<string, array> semua field, kunci => definisi (dengan 'group') */
    public function fields(): array
    {
        $all = [];
        foreach ($this->groups() as $group => $definition) {
            foreach ($definition['fields'] ?? [] as $key => $field) {
                $all[$key] = $field + ['group' => $group];
            }
        }

        return $all;
    }

    /** Timpakan nilai tersimpan ke config(). Dipanggil dari AppServiceProvider::boot(). */
    public function apply(): void
    {
        try {
            $stored = $this->stored();
        } catch (Throwable) {
            return;
        }

        foreach ($this->fields() as $key => $field) {
            if (! array_key_exists($key, $stored)) {
                continue;
            }
            $value = $this->cast($field, $stored[$key]);
            foreach ($field['config'] as $path) {
                config([$path => $value]);
            }
        }
    }

    /** Nilai yang sedang berlaku untuk form: tersimpan di database, atau bila belum ada, nilai config/.env. Rahasia selalu null. */
    public function current(string $key): mixed
    {
        $field = $this->fields()[$key] ?? null;
        if ($field === null || $field['type'] === 'secret') {
            return null;
        }

        return $this->cast($field, $this->effectiveString($key, $field));
    }

    public function hasSecret(string $key): bool
    {
        $path = $this->fields()[$key]['config'][0] ?? null;

        return filled($this->stored()[$key] ?? null) || ($path !== null && filled(config($path)));
    }

    /**
     * Simpan nilai satu grup (sudah divalidasi). Hanya nilai yang berbeda dari yang berlaku yang ditulis,
     * sehingga kunci yang tidak pernah diubah tetap mengikuti .env. Rahasia kosong = tidak diubah.
     *
     * @return list<string> kunci yang berubah (tanpa nilainya)
     */
    public function save(string $group, array $input): array
    {
        $changed = [];

        DB::transaction(function () use ($group, $input, &$changed) {
            foreach ($this->fields() as $key => $field) {
                if ($field['group'] !== $group) {
                    continue;
                }

                $secret = $field['type'] === 'secret';
                if ($secret && ! filled($input[$key] ?? null)) {
                    continue;
                }

                $new = $this->normalize($field, $input[$key] ?? null);
                if ($new === $this->effectiveString($key, $field)) {
                    continue;
                }

                AppSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $secret && $new !== null ? Crypt::encryptString($new) : $new, 'is_secret' => $secret],
                );
                $changed[] = $key;
            }
        });

        $this->flush();

        return $changed;
    }

    public function flush(): void
    {
        $this->memo = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // cache belum siap: tidak apa-apa, pembacaan berikutnya langsung ke database
        }
    }

    /** @return array<string, ?string> */
    private function stored(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $rows = Cache::get(self::CACHE_KEY);
        if (! is_array($rows)) {
            // Tabel belum ada (instalasi baru / sebelum migrate): jangan simpan hasil kosong di cache.
            if (! Schema::hasTable('app_settings')) {
                return [];
            }
            $rows = AppSetting::query()->get(['key', 'value', 'is_secret'])
                ->mapWithKeys(fn (AppSetting $row) => [$row->key => ['v' => $row->value, 's' => (bool) $row->is_secret]])
                ->all();
            // Yang di-cache tetap terenkripsi: cache (mis. driver database) tidak boleh berisi rahasia mentah.
            Cache::forever(self::CACHE_KEY, $rows);
        }

        $values = [];
        foreach ($rows as $key => $row) {
            if (! empty($row['s']) && $row['v'] !== null) {
                try {
                    $values[$key] = Crypt::decryptString($row['v']);
                } catch (DecryptException) {
                    // APP_KEY berganti: rahasia lama tak terbaca, pakai nilai .env sampai diisi ulang.
                    continue;
                }
            } else {
                $values[$key] = $row['v'];
            }
        }

        return $this->memo = $values;
    }

    private function effectiveString(string $key, array $field): ?string
    {
        $stored = $this->stored();
        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        $value = config($field['config'][0]);

        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private function normalize(array $field, mixed $value): ?string
    {
        return match ($field['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'number' => $value === null || $value === '' ? null : (string) (int) $value,
            default => $this->emptyToNull($field, is_string($value) ? trim($value) : $value),
        };
    }

    private function emptyToNull(array $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return ! empty($field['nullable']) ? null : '';
        }

        return (string) $value;
    }

    private function cast(array $field, ?string $raw): mixed
    {
        return match ($field['type']) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'number' => $raw === null || $raw === '' ? null : (int) $raw,
            default => ($raw === '' || $raw === null) && ! empty($field['nullable']) ? null : $raw,
        };
    }
}
