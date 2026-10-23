@extends('admin._layout')
@section('title', 'Unit Organisasi')
@section('actions')
  @can('organization.units.manage')
    <a href="{{ route('admin.organization.units.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah unit</a>
  @endcan
@endsection
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'units', 'compact' => true])</div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.organization.units.index') }}" class="row g-2 align-items-end">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="col-lg-5">
          <label for="search" class="form-label small mb-1">Cari unit</label>
          <div class="input-group">
            <span class="input-group-text bg-transparent"><x-icon name="search" size="15"/></span>
            <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Nama atau kode unit">
          </div>
        </div>
        <div class="col-sm-5 col-lg-3">
          <label for="type_id" class="form-label small mb-1">Jenis unit</label>
          <select id="type_id" name="type_id" class="form-select">
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
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Unit</th><th>Jenis</th><th>Unit induk</th><th>Turunan</th><th>Jabatan</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($units as $unit)
            <tr>
              <td><div class="fw-semibold">{{ $unit->name }}</div><div class="small text-secondary"><code>{{ $unit->code }}</code></div></td>
              <td>{{ $unit->type?->name ?? '—' }}</td>
              <td>{{ $unit->parent?->name ?? 'Unit induk' }}</td>
              <td>{{ number_format($unit->children_count, 0, ',', '.') }}</td>
              <td>{{ number_format($unit->positions_count, 0, ',', '.') }}</td>
              <td><span class="badge {{ $unit->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $unit->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.organization.units.show', $unit) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                @can('organization.units.manage')
                  <a href="{{ route('admin.organization.units.edit', $unit) }}" class="btn btn-sm btn-outline-primary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty-state py-5">
              <div class="fw-semibold">Tidak ada unit ditemukan</div>
              <div class="small text-secondary mt-1">Ubah filter atau buat unit organisasi baru.</div>
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($units->hasPages()) <div class="card-footer bg-transparent">{{ $units->links() }}</div> @endif
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

  @include('admin.organization.partials.add-unit-modal')
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
