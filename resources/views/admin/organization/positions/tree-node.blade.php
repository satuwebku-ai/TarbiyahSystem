@php
  $unit = $node['unit'];
  $children = $node['children'];
  $positionRows = $node['positions'];
  $groups = $node['groups'];
  $hasContent = count($children) + count($positionRows) + count($groups) > 0;
  $nodeId = 'org-node-'.$unit->id;
  $isOpen = $filtered || $depth < 3; // saat mencari, semua jalur dibuka
  $summary = function (array $names, int $limit = 4): string {
      if ($names === []) { return ''; }
      $shown = array_slice($names, 0, $limit);
      $rest = count($names) - count($shown);
      return implode(', ', $shown).($rest > 0 ? " +{$rest} lainnya" : '');
  };
@endphp
<li class="org-tree-item" role="treeitem" @if($hasContent) aria-expanded="{{ $isOpen ? 'true' : 'false' }}" @endif>
  <div class="org-tree-row {{ $node['match'] ? 'org-match' : '' }}">
    @if ($hasContent)
      <button type="button" class="org-tree-toggle {{ $isOpen ? '' : 'collapsed' }}"
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
        {{ $unit->type?->name ?? '—' }} · <code>{{ $unit->code }}</code> · {{ count($positionRows) }} jabatan
      </div>
    </div>

    @if ($unit->status !== 'active')
      <span class="badge badge-soft-secondary org-tree-meta">Nonaktif</span>
    @endif

    @if ($canManagePositions && $unit->status === 'active')
      <a href="{{ route('admin.organization.positions.create', ['unit_id' => $unit->id]) }}"
         class="btn btn-sm btn-outline-primary org-add-btn"
         title="Tambah jabatan di {{ $unit->name }}" aria-label="Tambah jabatan di {{ $unit->name }}">
        <x-icon name="plus" size="14"/><span class="d-none d-md-inline ms-1">Jabatan</span>
      </a>
    @endif
  </div>

  @if ($hasContent)
    <ul id="{{ $nodeId }}" class="org-tree-children collapse {{ $isOpen ? 'show' : '' }}" role="group">
      @foreach ($positionRows as $row)
        @php($position = $row['position'])
        <li class="org-tree-item" role="treeitem">
          <div class="org-tree-row org-pos-row {{ $row['match'] ? 'org-match' : '' }}">
            <span class="org-tree-toggle org-tree-leaf" aria-hidden="true"><span class="org-tree-dot"></span></span>
            <span class="org-tree-icon org-pos-icon"><x-icon name="shield" size="15"/></span>
            <div class="org-tree-info">
              <div class="fw-semibold text-truncate">{{ $position->name }}</div>
              <div class="small text-secondary text-truncate">
                {{ $row['people'] ? $summary($row['people']) : 'Belum ada yang ditugaskan' }}
              </div>
            </div>
            <span class="badge org-tree-meta {{ $position->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $position->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            @if ($canManagePositions)
              <a href="{{ route('admin.organization.positions.edit', $position) }}" class="btn btn-sm btn-light" aria-label="Ubah {{ $position->name }}">
                <x-icon name="edit" size="15"/>
              </a>
              <form method="POST" action="{{ route('admin.organization.positions.toggle', $position) }}" class="d-none d-md-inline m-0">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm {{ $position->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">{{ $position->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
              </form>
            @endif
          </div>
        </li>
      @endforeach

      @foreach ($groups as $group)
        <li class="org-tree-item" role="treeitem">
          <div class="org-tree-row org-pos-row {{ $group['match'] ? 'org-match' : '' }}">
            <span class="org-tree-toggle org-tree-leaf" aria-hidden="true"><span class="org-tree-dot"></span></span>
            <span class="org-tree-icon org-pos-icon"><x-icon name="users" size="15"/></span>
            <div class="org-tree-info">
              <div class="fw-semibold text-truncate">{{ $group['label'] }} <span class="fw-normal text-secondary">({{ count($group['names']) }})</span></div>
              <div class="small text-secondary text-truncate">{{ $summary($group['names'], 6) }}</div>
            </div>
          </div>
        </li>
      @endforeach

      @foreach ($children as $child)
        @include('admin.organization.positions.tree-node', ['node' => $child, 'depth' => $depth + 1])
      @endforeach
    </ul>
  @endif
</li>
