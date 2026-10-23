@php
  $unit = $node['unit'];
  $children = $node['children'];
  $rows = $node['rows'];
  $hasContent = count($children) + count($rows) > 0;
  $nodeId = 'org-node-'.$unit->id;
  $isOpen = $filtered || $depth < 3; // saat mencari/menyaring, semua jalur dibuka
  // Jabatan yang dipegang 2 orang atau lebih dijadikan simpul sendiri; nama pegawai menjadi turunannya.
  $groups = [];
  foreach ($rows as $row) {
    $positionId = $row['assignment']->organizational_position_id;
    $key = $positionId ?: 'none-'.$row['assignment']->id;
    $groups[$key] ??= ['position_id' => $positionId, 'name' => $row['assignment']->position?->name, 'rows' => []];
    $groups[$key]['rows'][] = $row;
  }
  $groups = collect($groups)->map(fn ($g) => $g + ['grouped' => $g['position_id'] && count($g['rows']) >= 2])->values();
  $activeCount = collect($rows)->filter(fn ($row) => $row['assignment']->status === 'active')->count();
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
        {{ $unit->type?->name ?? '—' }} · <code>{{ $unit->code }}</code> · {{ $activeCount }} penugasan aktif
      </div>
    </div>

    @if ($unit->status !== 'active')
      <span class="badge badge-soft-secondary org-tree-meta">Nonaktif</span>
    @else
      <a href="{{ route('admin.organization.assignments.create', ['unit_id' => $unit->id]) }}"
         class="btn btn-sm btn-outline-primary org-add-btn"
         title="Tambah penugasan di {{ $unit->name }}" aria-label="Tambah penugasan di {{ $unit->name }}">
        <x-icon name="plus" size="14"/><span class="d-none d-md-inline ms-1">Penugasan</span>
      </a>
    @endif
  </div>

  @if ($hasContent)
    <ul id="{{ $nodeId }}" class="org-tree-children collapse {{ $isOpen ? 'show' : '' }}" role="group">
      @foreach ($groups as $group)
        @if ($group['grouped'])
          @php
            $groupId = 'org-pos-'.$unit->id.'-'.$group['position_id'];
            $groupActive = collect($group['rows'])->filter(fn ($r) => $r['assignment']->status === 'active')->count();
            $groupOpen = $isOpen || collect($group['rows'])->contains(fn ($r) => $r['match']);
          @endphp
          <li class="org-tree-item" role="treeitem" aria-expanded="{{ $groupOpen ? 'true' : 'false' }}">
            <div class="org-tree-row org-group-row">
              <button type="button" class="org-tree-toggle {{ $groupOpen ? '' : 'collapsed' }}"
                      data-bs-toggle="collapse" data-bs-target="#{{ $groupId }}"
                      aria-controls="{{ $groupId }}" aria-label="Buka/tutup {{ $group['name'] }}">
                <x-icon name="chevron" size="14"/>
              </button>
              <span class="org-tree-icon org-pos-icon"><x-icon name="users" size="15"/></span>
              <div class="org-tree-info">
                <div class="fw-semibold text-truncate">{{ $group['name'] }}</div>
                <div class="small text-secondary text-truncate">{{ count($group['rows']) }} pegawai · {{ $groupActive }} aktif</div>
              </div>
            </div>
            <ul id="{{ $groupId }}" class="org-tree-children collapse {{ $groupOpen ? 'show' : '' }}" role="group">
              @foreach ($group['rows'] as $row)
                @include('admin.organization.assignments.assignment-row', ['assignment' => $row['assignment'], 'row' => $row, 'showPosition' => false])
              @endforeach
            </ul>
          </li>
        @else
          @foreach ($group['rows'] as $row)
            @include('admin.organization.assignments.assignment-row', ['assignment' => $row['assignment'], 'row' => $row, 'showPosition' => true])
          @endforeach
        @endif
      @endforeach

      @foreach ($children as $child)
        @include('admin.organization.assignments.tree-node', ['node' => $child, 'depth' => $depth + 1])
      @endforeach
    </ul>
  @endif
</li>
