@extends('admin._layout')
@section('title', 'Unit Organisasi')
@section('actions')
  @can('organization.units.manage')
    @if (($addable ?? []) || ($rootTypes ?? []))
      <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#orgAddModal"><x-icon name="plus" size="15" class="me-1"/>Tambah unit</button>
    @else
      <a href="{{ route('admin.organization.units.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah unit</a>
    @endif
  @endcan
@endsection
@section('content')
  @include('partials.admin.select2-assets')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'units', 'compact' => true])</div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" id="unitFilter" action="{{ route('admin.organization.units.index') }}" class="row g-2 align-items-end">
        <input type="hidden" name="view" value="{{ $view }}">
        @if ($view === 'tree')
        <div class="col-lg-5">
          <label for="search" class="form-label small mb-1">Cari unit</label>
          <div class="input-group">
            <span class="input-group-text bg-transparent"><x-icon name="search" size="15"/></span>
            <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Nama atau kode unit">
          </div>
        </div>
        @endif
        <div class="col-sm-5 col-lg-3">
          <label for="type_id" class="form-label small mb-1">Jenis unit</label>
          <select id="type_id" name="type_id" class="form-select js-select2" data-placeholder="Semua jenis">
            <option value="">Semua jenis</option>
            @foreach($types as $type)<option value="{{ $type->id }}" @selected(($filters['type_id'] ?? '') == $type->id)>{{ $type->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-sm-5 col-lg-2">
          <label for="status" class="form-label small mb-1">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Semua status</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
          </select>
        </div>
        <div class="col-sm-2 col-lg-2 d-flex gap-2">
          <button class="btn btn-primary flex-grow-1" type="submit">Terapkan</button>
          <a href="{{ route('admin.organization.units.index', ['view' => $view]) }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="small text-secondary">
      @if ($view === 'tree')
        @if ($filtered)
          Menampilkan {{ number_format($shownCount, 0, ',', '.') }} dari {{ number_format($totalCount, 0, ',', '.') }} unit (termasuk induk dari unit yang cocok).
        @else
          {{ number_format($totalCount, 0, ',', '.') }} unit dalam bagan.
        @endif
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
      'id' => 'tblUnits',
      'url' => route('admin.organization.units.index'),
      'filter' => '#unitFilter',
      'order' => [[0, 'asc']],
      'empty' => 'Tidak ada unit ditemukan. Ubah filter atau buat unit organisasi baru.',
      'columns' => [
        ['data' => 'name', 'title' => 'Unit'],
        ['data' => 'type_name', 'title' => 'Jenis'],
        ['data' => 'parent_name', 'title' => 'Unit induk'],
        ['data' => 'children_count', 'title' => 'Turunan', 'searchable' => false],
        ['data' => 'positions_count', 'title' => 'Jabatan', 'searchable' => false],
        ['data' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
  @else
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 mb-3">
        @if ($rootTypes)
          <button type="button" class="btn btn-sm btn-primary org-add-btn" data-bs-toggle="modal" data-bs-target="#orgAddModal"
                  data-parent-id="" data-parent-name="" data-types="{{ json_encode($rootTypes) }}">
            <x-icon name="plus" size="14" class="me-1"/>{{ count($rootTypes) === 1 ? $rootTypes[0]['short'] : 'Unit teratas' }}
          </button>
        @endif
        <button type="button" class="btn btn-sm btn-outline-secondary" id="orgExpandAll">Buka semua</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="orgCollapseAll">Tutup semua</button>
      </div>
      @if (count($tree))
        <ul class="org-tree" id="orgTree" role="tree">
          @foreach ($tree as $node)
            @include('admin.organization.units.tree-node', ['node' => $node, 'depth' => 0])
          @endforeach
        </ul>
      @else
        <div class="empty-state py-5">
          <div class="fw-semibold">Tidak ada unit ditemukan</div>
          <div class="small text-secondary mt-1">Ubah filter atau buat unit organisasi baru.</div>
        </div>
      @endif
    </div>
  </div>
  @endif

  @include('admin.organization.partials.add-unit-modal', ['returnTo' => 'units'])
@endsection

@push('scripts')
  @if ($view === 'tree')
    @include('admin.organization.partials.tree-styles')
    @include('admin.organization.partials.tree-scripts')
    <style>
      .org-tree-row.org-match { background:rgba(var(--bs-warning-rgb),.15); }
      .org-tree-meta { flex:0 0 auto; }
    </style>
  @endif
@endpush
