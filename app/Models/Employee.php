<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** Cadangan bila tabel jenis pegawai belum ada/kosong. */
    public const TYPES = ['teacher' => 'Guru', 'staff' => 'Tenaga kependidikan'];

    private static ?array $typeCache = null;

    public static function flushTypeCache(): void
    {
        self::$typeCache = null;
    }

    /**
     * Daftar jenis pegawai [kode => nama]. $onlyActive=true hanya jenis aktif; $include menyertakan
     * jenis milik pegawai yang sedang diubah walau sudah nonaktif.
     */
    public static function typeOptions(bool $onlyActive = true, ?string $include = null): array
    {
        if (self::$typeCache === null) {
            try {
                self::$typeCache = \App\Models\EmployeeType::query()->orderBy('name')->get(['code', 'name', 'is_active'])
                    ->map(fn ($t) => ['code' => $t->code, 'name' => $t->name, 'active' => (bool) $t->is_active])->all();
            } catch (\Throwable) {
                self::$typeCache = [];
            }
            if (self::$typeCache === []) {
                self::$typeCache = collect(self::TYPES)->map(fn ($name, $code) => ['code' => $code, 'name' => $name, 'active' => true])->values()->all();
            }
        }

        return collect(self::$typeCache)
            ->filter(fn ($t) => ! $onlyActive || $t['active'] || $t['code'] === $include)
            ->mapWithKeys(fn ($t) => [$t['code'] => $t['name']])->all();
    }

    protected $fillable = [
        'user_id', 'employee_number', 'name', 'employee_type', 'status',
        'email', 'phone', 'address', 'hire_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'hire_date' => 'date:Y-m-d',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions(false)[$this->employee_type] ?? $this->employee_type;
    }

    /** Akun login portal pegawai. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EmployeeStatusHistory::class);
    }
}
