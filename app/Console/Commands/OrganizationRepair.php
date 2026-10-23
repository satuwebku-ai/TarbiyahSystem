<?php

namespace App\Console\Commands;

use App\Models\OrganizationalAssignment;
use App\Models\OrganizationalPosition;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitHistory;
use App\Services\OrganizationHierarchy;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationRepair extends Command
{
    protected $signature = 'organization:repair
        {--code=* : Kode unit bermasalah yang akan dihapus (beserta seluruh turunannya)}
        {--apply : Jalankan penghapusan. Tanpa opsi ini hanya menampilkan laporan}';

    protected $description = 'Laporkan unit organisasi yang melanggar aturan hierarki dan hapus data contoh yang salah.';

    public function handle(OrganizationHierarchy $hierarchy): int
    {
        /** @var Collection<int, OrganizationalUnit> $units */
        $units = OrganizationalUnit::query()->with('type:id,slug,name')->orderBy('id')->get()->keyBy('id');
        $problems = $this->findProblems($units, $hierarchy);

        if ($problems === []) {
            $this->info('Struktur organisasi sudah sesuai aturan hierarki. Tidak ada yang perlu diperbaiki.');

            return self::SUCCESS;
        }

        $this->warn(count($problems).' unit bermasalah:');
        $this->table(
            ['ID', 'Kode', 'Nama', 'Jenis', 'Masalah'],
            collect($problems)->map(fn (string $reason, int $id) => [
                $id, $units[$id]->code, $units[$id]->name, $units[$id]->type?->name ?? '-', $reason,
            ])->values()->all(),
        );

        $codes = array_values(array_filter((array) $this->option('code')));
        if ($codes === []) {
            $this->line('Untuk menghapus, jalankan mis.: php artisan organization:repair --code=KODE1 --code=KODE2 --apply');

            return self::SUCCESS;
        }

        $targets = [];
        foreach ($codes as $code) {
            $unit = $units->firstWhere('code', $code);
            if (! $unit) {
                $this->error("Kode \"{$code}\" tidak ditemukan.");

                return self::FAILURE;
            }
            if (! isset($problems[$unit->id])) {
                $this->error("Unit \"{$unit->name}\" ({$code}) tidak bermasalah, jadi tidak akan dihapus oleh perintah ini.");

                return self::FAILURE;
            }
            foreach ([$unit->id, ...$this->descendantIds($units, $unit->id)] as $id) {
                $targets[$id] = $id;
            }
        }

        // Hapus dari tingkat terdalam agar relasi induk-anak tidak terhalang.
        $ordered = collect($targets)->sortByDesc(fn (int $id) => $this->depth($units, $id))->values()->all();

        $this->newLine();
        $this->warn('Unit yang akan dihapus (termasuk jabatan, penugasan, dan riwayatnya):');
        foreach ($ordered as $id) {
            $this->line("  - {$units[$id]->name} ({$units[$id]->code})");
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('Mode simulasi: belum ada yang dihapus. Tambahkan --apply untuk menjalankan.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Hapus unit di atas secara permanen?')) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($ordered) {
                OrganizationalAssignment::query()->whereIn('organizational_unit_id', $ordered)->delete();
                OrganizationalPosition::query()->whereIn('organizational_unit_id', $ordered)->delete();
                OrganizationalUnitHistory::query()->whereIn('organizational_unit_id', $ordered)->delete();
                DB::table('user_unit_access')->whereIn('organizational_unit_id', $ordered)->delete();

                foreach ($ordered as $id) {
                    OrganizationalUnit::query()->whereKey($id)->delete();
                }
            });
        } catch (QueryException $exception) {
            $this->error('Penghapusan dibatalkan: unit masih dipakai data lain (pegawai, program kerja, absensi, atau akademik). Tidak ada data yang berubah.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(count($ordered).' unit berhasil dihapus.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, OrganizationalUnit>  $units
     * @return array<int, string> id => alasan
     */
    private function findProblems(Collection $units, OrganizationHierarchy $hierarchy): array
    {
        $audit = $hierarchy->audit();
        $problems = [];

        foreach ($audit['own'] as $id) {
            $problems[$id] = $this->ownReason($units[$id], $units, $hierarchy);
        }

        // Jenis tunggal (Pengurus Besar): unit tertua dipertahankan, sisanya duplikat.
        $units->groupBy(fn (OrganizationalUnit $unit) => (string) $unit->type?->slug)->each(
            function (Collection $group, string $slug) use (&$problems, $hierarchy) {
                if (! $hierarchy->isSingleton($slug)) {
                    return;
                }
                $group->sortBy('id')->skip(1)->each(function (OrganizationalUnit $unit) use (&$problems) {
                    $problems[$unit->id] ??= "Duplikat {$unit->type?->name}: hanya boleh ada satu";
                });
            },
        );

        // Turunan dari unit bermasalah ikut ditandai.
        foreach ($units as $unit) {
            if (isset($problems[$unit->id])) {
                continue;
            }
            $cursor = $unit->parent_id ? $units->get($unit->parent_id) : null;
            for ($guard = 0; $cursor && $guard < 25; $guard++) {
                if (isset($problems[$cursor->id])) {
                    $problems[$unit->id] = "Berada di bawah unit bermasalah ({$cursor->name})";
                    break;
                }
                $cursor = $cursor->parent_id ? $units->get($cursor->parent_id) : null;
            }
        }

        foreach ($audit['chain'] as $id) {
            $problems[$id] ??= 'Rantai induk tidak valid';
        }

        ksort($problems);

        return $problems;
    }

    /** @param  Collection<int, OrganizationalUnit>  $units */
    private function ownReason(OrganizationalUnit $unit, Collection $units, OrganizationHierarchy $hierarchy): string
    {
        $allowed = $hierarchy->allowedParentSlugs($unit->type?->slug);
        $parent = $unit->parent_id ? $units->get($unit->parent_id) : null;

        if ($allowed === []) {
            return "{$unit->type?->name} tidak boleh punya induk (kini di bawah ".($parent?->name ?? '?').')';
        }
        if ($allowed === null) {
            return 'Unit induk tidak ditemukan';
        }

        $needed = implode(' / ', $allowed);

        return $parent
            ? 'Induk bertipe '.($parent->type?->name ?? '?').", seharusnya {$needed}"
            : "Wajib punya induk bertipe {$needed}";
    }

    /**
     * @param  Collection<int, OrganizationalUnit>  $units
     * @return int[]
     */
    private function descendantIds(Collection $units, int $rootId): array
    {
        $children = $units->groupBy('parent_id');
        $result = [];
        $frontier = [$rootId];

        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $parentId) {
                foreach ($children->get($parentId, collect()) as $child) {
                    if (! in_array($child->id, $result, true) && $child->id !== $rootId) {
                        $result[] = $child->id;
                        $next[] = $child->id;
                    }
                }
            }
            $frontier = $next;
        }

        return $result;
    }

    /** @param  Collection<int, OrganizationalUnit>  $units */
    private function depth(Collection $units, int $id): int
    {
        $depth = 0;
        $cursor = $units->get($id);
        while ($cursor && $cursor->parent_id && $depth < 25) {
            $depth++;
            $cursor = $units->get($cursor->parent_id);
        }

        return $depth;
    }
}
