@extends('admin._layout')
@section('title', 'Penugasan Organisasi')
@section('actions')
  @can('organization.assignments.manage')
    <a href="{{ route('admin.organization.assignments.create', array_filter(['unit_id' => $selectedUnitId])) }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah penugasan</a>
  @endcan
@endsection
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'assignments', 'compact' => true])</div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.organization.assignments.index') }}" class="row g-2 align-items-end">
        <input type="hidden" name="view" value="{{ $view }}">
        <div class="col-md-5">
          <label for="unit_id" class="form-label small mb-1">Unit</label>
          <select id="unit_id" name="unit_id" class="form-select">
            <option value="">Semua unit dalam cakupan</option>
            @foreach($unitOptions as $option)<option value="{{ $option['id'] }}" @selected($selectedUnitId == $option['id'])>{{ $option['label'] }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label for="status" class="form-label small mb-1">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Semua status</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
            <option value="ended" @selected(($filters['status'] ?? '') === 'ended')>Selesai</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="search" class="form-label small mb-1">Cari</label>
          <input id="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Nama, jabatan, atau unit">
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Terapkan</button>
          <a href="{{ route('admin.organization.assignments.index', ['view' => $view]) }}" class="btn btn-light">Reset</a>
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
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Nama</th><th>Jabatan</th><th>Unit</th><th>Periode</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($assignments as $assignment)
            <tr>
              <td>
                <div class="fw-semibold">{{ $assignment->assignee_name }}</div>
                <div class="small text-secondary">
                  {{ $assignment->employee?->employee_number ?: 'Belum terhubung ke data pegawai' }}
                  · {{ $assignment->admin ? '@'.($assignment->admin->username ?: $assignment->admin->name) : 'Tanpa akun pengelola' }}
                </div>
              </td>
              <td>{{ $assignment->position?->name }}</td>
              <td>{{ $assignment->unit?->name }}</td>
              <td class="text-nowrap">{{ $assignment->start_date?->format('d M Y') }} — {{ $assignment->end_date?->format('d M Y') ?? 'sekarang' }}</td>
              <td><span class="badge {{ $assignment->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $assignment->status === 'active' ? 'Aktif' : 'Selesai' }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.organization.assignments.edit', $assignment) }}" class="btn btn-sm btn-outline-primary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
                @if($assignment->status === 'active')
                  <form method="POST" action="{{ route('admin.organization.assignments.end', $assignment) }}" class="d-inline">
                    @csrf @method('PATCH')
                    <input type="hidden" name="end_date" value="{{ max(today()->toDateString(), $assignment->start_date->toDateString()) }}">
                    <button class="btn btn-sm btn-outline-warning" type="submit">Akhiri</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="empty-state py-5">Belum ada penugasan organisasi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($assignments->hasPages()) <div class="card-footer bg-transparent">{{ $assignments->links() }}</div> @endif
  </div>
  @else
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      @if (count($tree))
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="orgExpandAll">Buka semua</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="orgCollapseAll">Tutup semua</button>
          <span class="small text-secondary ms-md-2">{{ number_format($assignmentCount, 0, ',', '.') }} penugasan</span>
        </div>
        <ul class="org-tree" id="orgTree" role="tree">
          @foreach ($tree as $node)
            @include('admin.organization.assignments.tree-node', ['node' => $node, 'depth' => 0])
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
      .org-pos-row .org-tree-info .fw-semibold, .org-group-row .org-tree-info .fw-semibold { font-size:.92rem; }
      .org-group-row .org-tree-icon { background:rgba(var(--bs-primary-rgb),.1); color:var(--bs-primary); }
      .org-period { flex:0 0 auto; }
    </style>
  @endif
@endpush
