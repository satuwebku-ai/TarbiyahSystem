@php
  $unit = $node['unit'];
  $children = $node['children'];
  $hasChildren = count($children) > 0;
  $nodeId = 'org-node-'.$unit->id;
  $isOpen = $filtered || $depth < 2; // saat memfilter, semua jalur dibuka
  $childOptions = $addable[$unit->id] ?? [];
  $isInvalid = in_array($unit->id, $ownInvalidIds ?? [], true);
  $isMatch = $filtered && in_array($unit->id, $matchedIds, true);
@endphp
<li class="org-tree-item" role="treeitem" @if($hasChildren) aria-expanded="{{ $isOpen ? 'true' : 'false' }}" @endif>
  <div class="org-tree-row {{ $isMatch ? 'org-match' : '' }}">
    @if ($hasChildren)
      <button type="button"
              class="org-tree-toggle {{ $isOpen ? '' : 'collapsed' }}"
              data-bs-toggle="collapse" data-bs-target="#{{ $nodeId }}"
              aria-controls="{{ $nodeId }}" aria-label="Buka/tutup {{ $unit->name }}">
        <x-icon name="chevron" size="14"/>
      </button>
    @else
      <span class="org-tree-toggle org-tree-leaf" aria-hidden="true"><span class="org-tree-dot"></span></span>
    @endif

    <span class="org-tree-icon"><x-icon name="grid" size="16"/></span>

    <div class="org-tree-info">
      <a href="{{ route('admin.organization.units.show', $unit) }}" class="fw-semibold text-decoration-none">{{ $unit->name }}</a>
      <div class="small text-secondary text-truncate">
        {{ $unit->type?->name ?? '—' }} · <code>{{ $unit->code }}</code>
        @if ($hasChildren) · {{ count($children) }} turunan @endif
        · {{ number_format($unit->positions_count, 0, ',', '.') }} jabatan
      </div>
    </div>

    @if ($isInvalid)
      <span class="badge text-bg-warning org-tree-meta" title="Posisi unit ini tidak sesuai aturan hierarki. Ubah jenis atau induknya.">Perlu diperbaiki</span>
    @endif

    <span class="badge org-tree-meta {{ $unit->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">
      {{ $unit->status === 'active' ? 'Aktif' : 'Nonaktif' }}
    </span>

    @if ($childOptions)
      <button type="button" class="btn btn-sm btn-outline-primary org-add-btn"
              data-bs-toggle="modal" data-bs-target="#orgAddModal"
              data-parent-id="{{ $unit->id }}" data-parent-name="{{ $unit->name }}"
              data-types="{{ json_encode($childOptions) }}"
              title="Tambah unit di bawah {{ $unit->name }}"
              aria-label="Tambah unit di bawah {{ $unit->name }}">
        <x-icon name="plus" size="14"/>
        <span class="d-none d-md-inline ms-1">{{ count($childOptions) === 1 ? $childOptions[0]['short'] : 'Unit' }}</span>
      </button>
    @endif

    <a href="{{ route('admin.organization.units.show', $unit) }}" class="btn btn-sm btn-outline-secondary d-none d-sm-inline-block">Detail</a>
    @can('organization.units.manage')
      <a href="{{ route('admin.organization.units.edit', $unit) }}" class="btn btn-sm btn-light" aria-label="Ubah {{ $unit->name }}">
        <x-icon name="edit" size="15"/>
      </a>
    @endcan
  </div>

  @if ($hasChildren)
    <ul id="{{ $nodeId }}" class="org-tree-children collapse {{ $isOpen ? 'show' : '' }}" role="group">
      @foreach ($children as $child)
        @include('admin.organization.units.tree-node', ['node' => $child, 'depth' => $depth + 1])
      @endforeach
    </ul>
  @endif
</li>
