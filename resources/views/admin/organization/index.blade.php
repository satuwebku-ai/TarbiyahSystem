@extends('admin._layout')
@section('title', 'Struktur Organisasi')
@section('actions')
  @can('organization.units.manage')
    <a href="{{ route('admin.organization.units.create') }}" class="btn btn-primary btn-sm">
      <x-icon name="plus" size="15" class="me-1"/>Tambah unit
    </a>
  @endcan
@endsection

@section('content')
  @include('admin.organization.partials.setup-steps', ['active' => null])

  <div class="hero-card bg-page-hero text-white p-4 p-md-5">
    <div class="position-relative" style="z-index:1">
      <div class="small text-white-75 mb-1">Fase 03 · Fondasi organisasi</div>
      <h3 class="fw-bold mb-2">Struktur yang jelas, akses yang terarah</h3>
      <p class="text-white-75 mb-0" style="max-width:680px">
        Kelola unit bertingkat, jenis unit, jabatan, dan penugasan. Perubahan penting tersimpan sebagai riwayat.
      </p>
    </div>
  </div>

  <div class="row g-3">
    @foreach ([
      ['Unit organisasi', $unitCount, 'grid', 'card-stat-blue'],
      ['Unit aktif', $activeUnitCount, 'check', 'card-stat-emerald'],
      ['Jenis unit', $unitTypeCount, 'layout', 'card-stat-amber'],
      ['Jabatan', $positionCount, 'shield', 'card-stat-rose'],
    ] as [$label, $value, $icon, $class])
      <div class="col-6 col-xl-3">
        <div class="card {{ $class }} border shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="stat-icon"><x-icon :name="$icon"/></span>
            <div>
              <div class="text-secondary small">{{ $label }}</div>
              <div class="fs-3 fw-bold lh-1 mt-1">{{ number_format($value, 0, ',', '.') }}</div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold">Bagan unit organisasi</div>
            <div class="small text-secondary">Hubungan induk dan anak dapat diubah kapan saja.</div>
          </div>
          <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.organization.units.index') }}">Semua unit</a>
        </div>
        <div class="card-body">
          @if ($invalidUnits->isNotEmpty())
            <div class="alert alert-warning small" role="alert">
              <strong>{{ $invalidUnits->count() }} unit tidak sesuai aturan hierarki:</strong>
              {{ $invalidUnits->pluck('name')->implode(', ') }}.
              Ubah jenis atau induknya lewat tombol edit (mulai dari unit paling bawah). Unit ini belum dapat diberi turunan baru.
            </div>
          @endif
          @if (count($tree))
            <div class="d-flex flex-wrap gap-2 mb-3">
              @if ($rootTypes)
                <button type="button" class="btn btn-sm btn-primary org-add-btn" data-bs-toggle="modal" data-bs-target="#orgAddModal"
                        data-parent-id="" data-parent-name="" data-types="{{ json_encode($rootTypes) }}">
                  <x-icon name="plus" size="14" class="me-1"/>{{ count($rootTypes) === 1 ? $rootTypes[0]['short'] : 'Unit teratas' }}
                </button>
              @endif
              <button type="button" class="btn btn-sm btn-outline-secondary" id="orgExpandAll">Buka semua</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="orgCollapseAll">Tutup semua</button>
              <div class="input-group input-group-sm ms-auto" style="max-width:240px">
                <span class="input-group-text bg-transparent"><x-icon name="search" size="14"/></span>
                <input type="search" id="orgSearch" class="form-control" placeholder="Cari unit di bagan" aria-label="Cari unit di bagan">
              </div>
            </div>
            <ul class="org-tree" id="orgTree" role="tree">
              @foreach ($tree as $node)
                @include('admin.organization.partials.tree-node', ['node' => $node, 'depth' => 0])
              @endforeach
            </ul>
            <div id="orgTreeEmpty" class="text-secondary small text-center py-4 d-none">Tidak ada unit yang cocok.</div>
          @else
            <div class="empty-state py-5">
              <x-icon name="grid" size="28" class="text-secondary mb-2"/>
              <div class="fw-semibold">Struktur belum dibuat</div>
              <p class="text-secondary small mb-3">Tambahkan jenis unit terlebih dahulu, lalu buat unit induk dan unit turunannya.</p>
              @can('organization.types.manage')
                <a href="{{ route('admin.organization.unit-types.create') }}" class="btn btn-outline-primary btn-sm">Tambah jenis unit</a>
              @endcan
              @if ($rootTypes)
                <button type="button" class="btn btn-primary btn-sm org-add-btn" data-bs-toggle="modal" data-bs-target="#orgAddModal"
                        data-parent-id="" data-parent-name="" data-types="{{ json_encode($rootTypes) }}">
                  <x-icon name="plus" size="14" class="me-1"/>Buat {{ count($rootTypes) === 1 ? $rootTypes[0]['short'] : 'unit pertama' }}
                </button>
              @endif
            </div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold">Riwayat perubahan</div>
            <div class="small text-secondary">Aktivitas terbaru pada struktur organisasi.</div>
          </div>
          <x-icon name="clock" size="17" class="text-secondary"/>
        </div>
        <div class="card-body p-0">
          @forelse ($recentChanges as $change)
            <div class="px-3 py-3 border-bottom d-flex gap-3">
              <span class="avatar avatar-sm flex-shrink-0">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($change->actor?->name ?? 'S', 0, 1)) }}</span>
              <div class="min-w-0">
                <div class="fw-semibold small">{{ $change->summary }}</div>
                <div class="small text-secondary text-truncate">
                  {{ $change->unit?->name ?? 'Unit' }} · {{ $change->actor?->name ?? 'Sistem' }}
                </div>
                <div class="small text-secondary">{{ $change->created_at?->diffForHumans() }}</div>
              </div>
            </div>
          @empty
            <div class="empty-state py-5">
              <div class="fw-semibold">Belum ada perubahan</div>
              <div class="small text-secondary mt-1">Riwayat akan muncul saat unit, jabatan, atau penugasan diperbarui.</div>
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    @can('organization.types.manage')
      <div class="col-md-4">
        <a href="{{ route('admin.organization.unit-types.index') }}" class="card border-0 shadow-sm text-decoration-none h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="stat-icon"><x-icon name="layout"/></span>
            <div><div class="fw-semibold text-body">Jenis unit</div><div class="small text-secondary">Buat jenis yang sesuai dengan organisasi.</div></div>
          </div>
        </a>
      </div>
    @endcan
    @can('organization.positions.manage')
      <div class="col-md-4">
        <a href="{{ route('admin.organization.positions.index') }}" class="card border-0 shadow-sm text-decoration-none h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="stat-icon"><x-icon name="shield"/></span>
            <div><div class="fw-semibold text-body">Jabatan</div><div class="small text-secondary">Atur jabatan pada tiap unit.</div></div>
          </div>
        </a>
      </div>
    @endcan
    @can('organization.assignments.manage')
      <div class="col-md-4">
        <a href="{{ route('admin.organization.assignments.index') }}" class="card border-0 shadow-sm text-decoration-none h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="stat-icon"><x-icon name="users"/></span>
            <div><div class="fw-semibold text-body">Penugasan</div><div class="small text-secondary">{{ number_format($assignmentCount, 0, ',', '.') }} penugasan aktif saat ini.</div></div>
          </div>
        </a>
      </div>
    @endcan
  </div>

  @include('admin.organization.partials.add-unit-modal')
@endsection

@push('scripts')
@include('admin.organization.partials.tree-styles')
@include('admin.organization.partials.tree-scripts')
@endpush
