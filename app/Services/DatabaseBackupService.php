<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Cadangan & pemulihan logis (per baris) lewat lapisan database Laravel, sehingga berjalan sama
 * di MySQL maupun SQLite. Format: berkas .jsonl.gz — baris pertama berisi metadata
 * (versi format + daftar migrasi), baris berikutnya {"t": tabel, "r": baris}.
 *
 * Catatan operasional: ini cocok untuk data yayasan berukuran kecil-menengah. Untuk produksi
 * besar, lengkapi dengan mysqldump/snapshot server (lihat Fase 12). Berkas cadangan memuat data
 * pribadi dan hash kata sandi — simpan di disk privat, jangan di folder publik.
 */
class DatabaseBackupService
{
    public const FORMAT = 1;

    /** Tabel sementara yang tidak perlu dicadangkan/dipulihkan. */
    public const EXCLUDED = ['sessions', 'cache', 'cache_locks'];

    /** Menulis cadangan ke $path dan mengembalikan jumlah baris per tabel. */
    public function backup(string $path): array
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException("Folder cadangan tidak dapat dibuat: {$dir}");
        }

        $handle = gzopen($path, 'wb9');
        if ($handle === false) {
            throw new RuntimeException("Berkas cadangan tidak dapat ditulis: {$path}");
        }

        $counts = [];
        try {
            $this->writeLine($handle, [
                'format' => self::FORMAT,
                'created_at' => now()->toIso8601String(),
                'migrations' => $this->migrationNames(),
            ]);

            // Snapshot konsisten: seluruh tabel dibaca dalam satu transaksi.
            DB::transaction(function () use ($handle, &$counts) {
                foreach ($this->tables() as $table) {
                    $counts[$table] = 0;
                    $query = DB::table($table);
                    $key = $this->orderColumn($table);
                    $iterate = $key ? $query->orderBy($key)->lazyById(500, $key) : $query->cursor();
                    foreach ($iterate as $row) {
                        $this->writeLine($handle, ['t' => $table, 'r' => (array) $row]);
                        $counts[$table]++;
                    }
                }
            });
        } finally {
            gzclose($handle);
        }

        return $counts;
    }

    /**
     * Memulihkan cadangan: seluruh isi tabel (kecuali daftar pengecualian) diganti isi cadangan.
     * Semua-atau-tidak-sama-sekali: bila ada kegagalan, transaksi dibatalkan dan data lama utuh.
     */
    public function restore(string $path): array
    {
        $meta = $this->readMeta($path);

        if (($meta['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('Format berkas cadangan tidak dikenali.');
        }
        if ($this->migrationNames() !== $meta['migrations']) {
            throw new RuntimeException('Versi skema berbeda dari cadangan (daftar migrasi tidak sama). Jalankan migrasi yang sesuai lebih dulu.');
        }

        $tables = $this->tables();
        $counts = array_fill_keys($tables, 0);

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($path, $tables, &$counts) {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }

                $handle = gzopen($path, 'rb');
                $buffer = [];
                $flush = function () use (&$buffer) {
                    foreach ($buffer as $table => $rows) {
                        foreach (array_chunk($rows, 200) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                    $buffer = [];
                };

                try {
                    gzgets($handle); // lewati metadata
                    $pending = 0;
                    while (($line = gzgets($handle)) !== false) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        if (! in_array($entry['t'], $tables, true)) {
                            throw new RuntimeException("Cadangan memuat tabel yang tidak dikenal: {$entry['t']}");
                        }
                        $buffer[$entry['t']][] = $entry['r'];
                        $counts[$entry['t']]++;
                        if (++$pending >= 1000) {
                            $flush();
                            $pending = 0;
                        }
                    }
                    $flush();
                } finally {
                    gzclose($handle);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $counts;
    }

    public function readMeta(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas cadangan tidak ditemukan: {$path}");
        }
        $handle = gzopen($path, 'rb');
        $line = $handle ? gzgets($handle) : false;
        if ($handle) {
            gzclose($handle);
        }
        $meta = $line ? json_decode($line, true) : null;
        if (! is_array($meta)) {
            throw new RuntimeException('Berkas cadangan rusak atau bukan berkas cadangan aplikasi ini.');
        }

        return $meta;
    }

    /** @return string[] tabel yang dicadangkan, tanpa tabel migrasi & tabel sementara */
    public function tables(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn ($t) => $t === 'migrations' || in_array($t, self::EXCLUDED, true) || str_starts_with($t, 'sqlite_'))
            ->sort()->values()->all();
    }

    /** @return string[] */
    private function migrationNames(): array
    {
        return DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
    }

    private function orderColumn(string $table): ?string
    {
        return Schema::hasColumn($table, 'id') ? 'id' : null;
    }

    private function writeLine($handle, array $data): void
    {
        gzwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
    }
}
