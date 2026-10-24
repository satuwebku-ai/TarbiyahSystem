@extends('admin._layout')
@section('title', 'Jabatan Organisasi')
@section('actions')
  @can('organization.positions.manage')
    <a href="{{ route('admin.organization.positions.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah jabatan</a>
  @endcan
@endsection
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'positions', 'compact' => true])</div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" id="positionFilter" action="{{ route('admin.organization.positions.index') }}" class="row g-2 align-items-end">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="col-md-5">
          <label for="unit_id" class="form-label small mb-1">Filter unit</label>
          <select id="unit_id" name="unit_id" class="form-select">
            <option value="">Semua unit dalam cakupan</option>
            @if ($view === 'tree')
              @foreach($unitOptions as $option)<option value="{{ $option['id'] }}" @selected($selectedUnitId == $option['id'])>{{ $option['label'] }}</option>@endforeach
            @else
              @foreach($units as $unit)<option value="{{ $unit->id }}" @selected($selectedUnitId == $unit->id)>{{ $unit->name }} · {{ $unit->code }}</option>@endforeach
            @endif
          </select>
        </div>
        @if ($view === 'tree')
          <div class="col-md-4">
            <label for="search" class="form-label small mb-1">Cari</label>
            <input id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Jabatan, unit, atau nama orang">
          </div>
        @endif
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Terapkan</button>
          <a href="{{ route('admin.organization.positions.index', ['view' => $view]) }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-secondary">
      @if ($view === 'tree' && count($scopeRoots))
        Cakupan Anda: <strong>{{ implode(', ', $scopeRoots) }}</strong> beserta unit di bawahnya.
      @endif
    </div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Pilih tampilan">
      <a href="{{ request()->fullUrlWithQuery(['view' => 'tree', 'page' => null]) }}" class="btn {{ $view === 'tree' ? 'btn-primary' : 'btn-outline-secondary' }}">Bagan</a>
      <a href="{{ request()->fullUrlWithQuery(['view' => 'table', 'page' => null]) }}" class="btn {{ $view === 'table' ? 'btn-primary' : 'btn-outline-secondary' }}">Tabel</a>
    </div>
  </div>

  @if ($view === 'table')
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblPositions',
      'url' => route('admin.organization.positions.index'),
      'filter' => '#positionFilter',
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada jabatan di unit yang dapat Anda akses.',
      'columns' => [
        ['data' => 'name', 'title' => 'Jabatan'],
        ['data' => 'unit_name', 'title' => 'Unit'],
        ['data' => 'assignments_count', 'title' => 'Penugasan', 'searchable' => false],
        ['data' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
  @else
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      @if (count($tree))
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="orgExpandAll">Buka semua</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="orgCollapseAll">Tutup semua</button>
        </div>
        <ul class="org-tree" id="orgTree" role="tree">
          @foreach ($tree as $node)
            @include('admin.organization.positions.tree-node', ['node' => $node, 'depth' => 0])
          @endforeach
        </ul>
      @else
        <div class="empty-state py-5">
          <div class="fw-semibold">Tidak ada data ditemukan</div>
          <div class="small text-secondary mt-1">Ubah pencarian atau filter, atau Anda belum memiliki akses ke unit organisasi.</div>
        </div>
      @endif
    </div>
  </div>
  @endif
@endsection

@push('scripts')
  @if ($view === 'tree')
    @include('admin.organization.partials.tree-styles')
    @include('admin.organization.partials.tree-scripts')
    <style>
      .org-tree-row.org-match { background:rgba(var(--bs-warning-rgb),.15); }
      .org-tree-meta { flex:0 0 auto; }
      .org-pos-icon { background:rgba(var(--bs-secondary-rgb),.12); color:var(--bs-secondary-color); }
      .org-pos-row .org-tree-info .fw-semibold { font-size:.92rem; }
    </style>
  @endif
@endpush
