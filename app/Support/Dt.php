<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pembantu kecil untuk respons Yajra DataTables (server-side).
 * Semua teks yang berasal dari data selalu di-escape (e()) sebelum dijadikan HTML kolom.
 */
class Dt
{
    /** Permintaan ajax dari DataTables selalu membawa parameter "draw". */
    public static function wants(Request $request): bool
    {
        return $request->has('draw');
    }

    /** Judul tebal + keterangan kecil di bawahnya. */
    public static function stack(?string $title, ?string $sub = null, string $fallback = '—'): string
    {
        return '<div class="fw-semibold">'.e($title ?? '').'</div>'
            .'<div class="small text-secondary">'.e(($sub !== null && $sub !== '') ? $sub : $fallback).'</div>';
    }

    public static function badge(bool $active, string $on = 'Aktif', string $off = 'Nonaktif'): string
    {
        return '<span class="badge '.($active ? 'badge-soft-success' : 'badge-soft-secondary').'">'.e($active ? $on : $off).'</span>';
    }

    /** Badge bebas dengan kelas kustom (mis. badge-soft-warning). */
    public static function pill(string $text, string $class = 'badge-soft-secondary'): string
    {
        return '<span class="badge '.e($class).'">'.e($text).'</span>';
    }

    public static function num(int|float|null $n): string
    {
        return number_format((float) ($n ?? 0), 0, ',', '.');
    }

    public static function muted(?string $text, string $fallback = '—'): string
    {
        return $text !== null && $text !== '' ? e($text) : '<span class="text-secondary">'.e($fallback).'</span>';
    }

    /**
     * Urutkan menurut kolom milik tabel relasi (belongsTo) lewat subquery, mis.
     * orderColumn('unit_name', fn ($q, $dir) => Dt::orderByRelated($q, $dir, 'organizational_units', 'organizational_unit_id', 'organizational_positions'))
     */
    public static function orderByRelated($query, string $dir, string $relatedTable, string $foreignKey, string $ownTable, string $column = 'name'): void
    {
        $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
        $query->orderBy(
            DB::table("{$relatedTable} as dt_rel")->select("dt_rel.{$column}")->whereColumn("dt_rel.id", "{$ownTable}.{$foreignKey}")->limit(1),
            $dir
        );
    }

    /** Saring baris yang relasinya (belongsTo) cocok dengan kata kunci pada salah satu kolom $columns. */
    public static function filterRelated($query, string $foreignKey, string $relatedTable, array $columns, string $keyword): void
    {
        $query->whereIn($foreignKey, function ($sub) use ($relatedTable, $columns, $keyword) {
            $sub->select('id')->from($relatedTable)->where(function ($w) use ($columns, $keyword) {
                foreach ($columns as $col) {
                    $w->orWhere($col, 'like', "%{$keyword}%");
                }
            });
        });
    }
}
