<?php

namespace App\Services;

use App\Models\OrganizationalUnit;
use App\Models\OrganizationalUnitType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Aturan hierarki unit organisasi. Dipakai oleh validasi server, form, dan bagan,
 * sehingga aturan hanya ditulis di config/organization.php.
 */
class OrganizationHierarchy
{
    /** @var array{own: int[], chain: int[]}|null */
    private ?array $audit = null;

    /** @return array<string, array<string, mixed>> */
    public function levels(): array
    {
        return config('organization.levels', []);
    }

    /**
     * null  = jenis bebas (tidak diatur)
     * []    = hanya boleh di tingkat teratas
     * [..]  = slug jenis induk yang diizinkan
     *
     * @return string[]|null
     */
    public function allowedParentSlugs(?string $slug): ?array
    {
        if ($slug === null) {
            return null;
        }

        return $this->levels()[$slug]['parents'] ?? null;
    }

    public function isSingleton(?string $slug): bool
    {
        return (bool) ($this->levels()[$slug ?? '']['singleton'] ?? false);
    }

    /**
     * Unit yang aturannya dilanggar oleh dirinya sendiri (own) dan unit yang
     * rantai induknya mengandung pelanggaran (chain, termasuk own).
     *
     * @return array{own: int[], chain: int[]}
     */
    public function audit(): array
    {
        if ($this->audit !== null) {
            return $this->audit;
        }

        $units = OrganizationalUnit::query()
            ->with('type:id,slug')
            ->get(['id', 'type_id', 'parent_id'])
            ->keyBy('id');

        $own = [];
        $chain = [];

        $isChainBroken = function (OrganizationalUnit $unit) use (&$isChainBroken, &$own, &$chain, $units): bool {
            if (array_key_exists($unit->id, $chain)) {
                return $chain[$unit->id];
            }
            $chain[$unit->id] = true; // sementara: siklus dianggap rusak

            $parent = $unit->parent_id ? $units->get($unit->parent_id) : null;
            $allowed = $this->allowedParentSlugs($unit->type?->slug);

            if ($allowed === null) {
                $ownBroken = $unit->parent_id !== null && $parent === null;
            } elseif ($allowed === []) {
                $ownBroken = $unit->parent_id !== null;
            } else {
                $ownBroken = $parent === null || ! in_array($parent->type?->slug, $allowed, true);
            }

            if ($ownBroken) {
                $own[] = $unit->id;
            }

            $parentBroken = $parent ? $isChainBroken($parent) : false;

            return $chain[$unit->id] = ($ownBroken || $parentBroken);
        };

        foreach ($units as $unit) {
            $isChainBroken($unit);
        }

        return $this->audit = [
            'own' => array_values(array_unique($own)),
            'chain' => array_keys(array_filter($chain)),
        ];
    }

    /** @return int[] */
    public function brokenUnitIds(): array
    {
        return $this->audit()['chain'];
    }

    /**
     * Pastikan kombinasi jenis unit dan induk sah. Dipanggil pada simpan/ubah.
     */
    public function validateParent(OrganizationalUnitType $type, mixed $parentId, ?OrganizationalUnit $current = null): void
    {
        $parentId = ($parentId === null || $parentId === '') ? null : (int) $parentId;
        $allowed = $this->allowedParentSlugs($type->slug);
        $label = $this->levels()[$type->slug]['short'] ?? $type->name;

        if ($allowed === []) {
            if ($parentId !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => "{$label} merupakan unit paling atas dan tidak boleh memiliki unit induk.",
                ]);
            }

            if ($this->isSingleton($type->slug)) {
                $exists = OrganizationalUnit::query()
                    ->whereHas('type', fn ($query) => $query->where('slug', $type->slug))
                    ->when($current, fn ($query) => $query->whereKeyNot($current->id))
                    ->exists();

                if ($exists) {
                    throw ValidationException::withMessages([
                        'type_id' => "{$label} sudah ada. Struktur hanya boleh memiliki satu {$label}.",
                    ]);
                }
            }

            return;
        }

        if ($parentId === null) {
            if ($allowed === null) {
                return; // jenis bebas boleh menjadi unit teratas
            }

            throw ValidationException::withMessages([
                'parent_id' => "{$label} wajib memiliki unit induk bertipe {$this->typeNames($allowed)}.",
            ]);
        }

        $parent = OrganizationalUnit::query()->with('type:id,slug,name')->find($parentId);

        if ($allowed !== null && (! $parent || ! in_array($parent->type?->slug, $allowed, true))) {
            throw ValidationException::withMessages([
                'parent_id' => "Unit induk untuk {$label} harus bertipe {$this->typeNames($allowed)}.",
            ]);
        }

        if (! $parent || in_array($parent->id, $this->brokenUnitIds(), true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Unit induk berada pada rantai hierarki yang tidak valid. Perbaiki unit induknya terlebih dahulu.',
            ]);
        }
    }

    /**
     * Mengganti jenis unit tidak boleh membuat turunannya melanggar aturan.
     */
    public function validateChildrenCompatible(OrganizationalUnit $unit, OrganizationalUnitType $newType): void
    {
        $unit->children()->with('type:id,slug,name')->get()->each(function (OrganizationalUnit $child) use ($newType) {
            $allowed = $this->allowedParentSlugs($child->type?->slug);

            if ($allowed !== null && ! in_array($newType->slug, $allowed, true)) {
                throw ValidationException::withMessages([
                    'type_id' => "Jenis unit tidak dapat diubah: turunan \"{$child->name}\" ({$child->type?->name}) membutuhkan induk bertipe {$this->typeNames($allowed)}.",
                ]);
            }
        });
    }

    /**
     * Pilihan tombol "+" pada bagan: jenis yang boleh ditambahkan di bawah tiap unit
     * dan jenis yang boleh dibuat sebagai unit tingkat teratas.
     *
     * @param  Collection<int, OrganizationalUnit>  $units  harus memuat relasi type
     * @return array{0: array<int, array<int, array<string, mixed>>>, 1: array<int, array<string, mixed>>}
     */
    public function addOptions(Collection $units, bool $canManage, bool $canCreateRoot): array
    {
        if (! $canManage) {
            return [[], []];
        }

        $activeTypes = OrganizationalUnitType::query()->where('is_active', true)->orderBy('name')->get();
        $chain = $this->audit()['chain'];
        $addable = [];

        foreach ($units as $unit) {
            // Unit nonaktif atau berada pada rantai yang rusak tidak boleh diberi turunan baru.
            if ($unit->status !== 'active' || ! $unit->type || in_array($unit->id, $chain, true)) {
                continue;
            }
            $options = $this->childTypeOptions($unit->type->slug, $activeTypes);
            if ($options !== []) {
                $addable[$unit->id] = $options;
            }
        }

        $rootTypes = $canCreateRoot
            ? $this->rootTypeOptions($activeTypes, OrganizationalUnit::query()->exists())
            : [];

        return [$addable, $rootTypes];
    }

    /**
     * Jenis yang boleh dibuat tepat di bawah unit dengan slug tertentu.
     *
     * @param  Collection<int, OrganizationalUnitType>  $activeTypes
     * @return array<int, array<string, mixed>>
     */
    public function childTypeOptions(string $parentSlug, Collection $activeTypes): array
    {
        return $activeTypes
            ->filter(function (OrganizationalUnitType $type) use ($parentSlug) {
                $allowed = $this->allowedParentSlugs($type->slug);

                return $allowed === null || in_array($parentSlug, $allowed, true);
            })
            ->map(fn (OrganizationalUnitType $type) => $this->typeOption($type))
            ->values()
            ->all();
    }

    /**
     * Jenis yang boleh dibuat sebagai unit tingkat teratas.
     *
     * @param  Collection<int, OrganizationalUnitType>  $activeTypes
     * @return array<int, array<string, mixed>>
     */
    public function rootTypeOptions(Collection $activeTypes, bool $hasUnits): array
    {
        $usedSlugs = OrganizationalUnitType::query()->whereHas('units')->pluck('slug')->all();

        return $activeTypes
            ->filter(function (OrganizationalUnitType $type) use ($usedSlugs, $hasUnits) {
                $allowed = $this->allowedParentSlugs($type->slug);

                if ($allowed === []) {
                    return ! ($this->isSingleton($type->slug) && in_array($type->slug, $usedSlugs, true));
                }

                return $allowed === null && ! $hasUnits;
            })
            ->map(fn (OrganizationalUnitType $type) => $this->typeOption($type))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function typeOption(OrganizationalUnitType $type): array
    {
        $level = $this->levels()[$type->slug] ?? [];

        return [
            'id' => $type->id,
            'slug' => $type->slug,
            'name' => $type->name,
            'short' => $level['short'] ?? $type->name,
            'title' => $level['title'] ?? $type->name,
            'guidance' => $level['guidance'] ?? 'Jenis unit ini tidak memiliki aturan khusus; isi data unit dengan lengkap.',
            'name_label' => $level['name_label'] ?? 'Nama unit',
            'name_placeholder' => $level['name_placeholder'] ?? 'Contoh: Nama unit organisasi',
            'code_placeholder' => $level['code_placeholder'] ?? 'Contoh: UNIT-001',
        ];
    }

    /**
     * Data aturan untuk skrip form.
     *
     * @return array<string, array<string, mixed>>
     */
    public function formConfig(): array
    {
        $config = [];
        foreach ($this->levels() as $slug => $level) {
            $config[$slug] = [
                'parents' => $level['parents'],
                'title' => $level['title'],
                'guidance' => $level['guidance'],
                'name_label' => $level['name_label'],
                'name_placeholder' => $level['name_placeholder'],
                'code_placeholder' => $level['code_placeholder'],
            ];
        }

        return $config;
    }

    /** @param  string[]  $slugs */
    private function typeNames(array $slugs): string
    {
        return collect($slugs)
            ->map(fn (string $slug) => OrganizationalUnitType::query()->where('slug', $slug)->value('name')
                ?? ($this->levels()[$slug]['title'] ?? $slug))
            ->implode(' / ');
    }
}
