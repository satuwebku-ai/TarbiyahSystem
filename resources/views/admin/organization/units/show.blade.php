@extends('admin._layout')
@section('title', $unit->name)
@section('parent', 'Unit Organisasi')
@section('parent_url', route('admin.organization.units.index'))
@section('actions')
  @can('organization.units.manage')
    <a href="{{ route('admin.organization.units.edit', $unit) }}" class="btn btn-outline-primary btn-sm"><x-icon name="edit" size="15" class="me-1"/>Ubah unit</a>
  @endcan
  @can('organization.positions.manage')
    <a href="{{ route('admin.organization.positions.create', ['unit_id' => $unit->id]) }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah jabatan</a>
  @endcan
@endsection
@section('content')
  @php
    $activeAssignments = $unit->assignments->where('status', 'active')->count();
  @endphp
  @if ($unit->positions->isEmpty() || $activeAssignments === 0)
    <div class="alert alert-primary d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <div class="fw-semibold">Langkah berikutnya untuk unit ini</div>
        <div class="small">
          {{ $unit->positions->isEmpty() ? 'Unit sudah dibuat. Tambahkan jabatan pada unit ini.' : 'Jabatan sudah ada. Tugaskan seseorang pada jabatan tersebut.' }}
        </div>
      </div>
      <div class="d-flex gap-2">
        @if ($unit->positions->isEmpty())
          @can('organization.positions.manage')
            <a href="{{ route('admin.organization.positions.create', ['unit_id' => $unit->id]) }}" class="btn btn-primary btn-sm">Tambah jabatan</a>
          @endcan
        @else
          @can('organization.assignments.manage')
            <a href="{{ route('admin.organization.assignments.create', ['unit_id' => $unit->id]) }}" class="btn btn-primary btn-sm">Tambah penugasan</a>
          @endcan
        @endif
      </div>
    </div>
  @endif
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
              <div class="small text-secondary">{{ $unit->type?->name }} · <code>{{ $unit->code }}</code></div>
              <h3 class="h4 fw-bold mt-2 mb-2">{{ $unit->name }}</h3>
              <div class="text-secondary">{{ $unit->description ?: 'Belum ada keterangan unit.' }}</div>
            </div>
            <span class="badge {{ $unit->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $unit->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
          </div>
          <hr>
          <div class="row g-3">
            <div class="col-sm-6"><div class="small text-secondary">Unit induk</div><div class="fw-semibold">{{ $unit->parent?->name ?? 'Unit paling atas' }}</div></div>
            <div class="col-sm-6"><div class="small text-secondary">Alamat</div><div class="fw-semibold">{{ $unit->address ?: '—' }}</div></div>
            <div class="col-sm-6"><div class="small text-secondary">Dibuat</div><div class="fw-semibold">{{ $unit->created_at?->translatedFormat('d F Y') }}</div></div>
            <div class="col-sm-6"><div class="small text-secondary">Terakhir diperbarui</div><div class="fw-semibold">{{ $unit->updated_at?->translatedFormat('d F Y H:i') }}</div></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Unit turunan</div>
        <div class="card-body">
          @forelse($unit->children as $child)
            <a href="{{ route('admin.organization.units.show', $child) }}" class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none">
              <span><span class="fw-semibold text-body">{{ $child->name }}</span><span class="d-block small text-secondary">{{ $child->type?->name }} · {{ $child->code }}</span></span>
              <x-icon name="chevron" size="16" class="text-secondary"/>
            </a>
          @empty
            <div class="small text-secondary py-3">Belum ada unit turunan.</div>
          @endforelse
          @can('organization.units.manage')
            <a href="{{ route('admin.organization.units.create', ['parent_id' => $unit->id]) }}" class="btn btn-sm btn-outline-primary mt-3">Tambah unit turunan</a>
          @endcan
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Jabatan</span>
          @can('organization.positions.manage')<a href="{{ route('admin.organization.positions.index', ['unit_id' => $unit->id]) }}" class="small text-decoration-none">Kelola</a>@endcan
        </div>
        <div class="card-body p-0">
          @forelse($unit->positions as $position)
            <div class="px-3 py-2 border-bottom d-flex justify-content-between">
              <div><div class="fw-semibold">{{ $position->name }}</div><div class="small text-secondary">{{ $position->description ?: '—' }}</div></div>
              <span class="badge {{ $position->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $position->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </div>
          @empty
            <div class="empty-state py-4">Belum ada jabatan pada unit ini.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Penugasan</span>
          @can('organization.assignments.manage')<a href="{{ route('admin.organization.assignments.index', ['unit_id' => $unit->id]) }}" class="small text-decoration-none">Kelola</a>@endcan
        </div>
        <div class="card-body p-0">
          @php
            // Jabatan dengan 2 penugasan atau lebih ditampilkan sebagai kelompok; nama pegawai menjadi turunannya.
            $assignmentGroups = $unit->assignments->groupBy(fn ($a) => $a->organizational_position_id ?: 'none-'.$a->id)->values();
          @endphp
          @forelse($assignmentGroups as $group)
            @if ($group->count() >= 2 && $group->first()->organizational_position_id)
              <div class="border-bottom">
                <div class="px-3 py-2 d-flex justify-content-between align-items-center gap-3 bg-body-tertiary">
                  <div class="fw-semibold">{{ $group->first()->position?->name }}</div>
                  <span class="small text-secondary">{{ $group->count() }} pegawai</span>
                </div>
                @foreach($group as $assignment)
                  <div class="py-2 ps-4 pe-3 d-flex justify-content-between gap-3 border-top" style="margin-left:.75rem;border-left:2px solid rgba(128,128,128,.35)">
                    <div><div class="fw-semibold">{{ $assignment->assignee_name }}</div><div class="small text-secondary">{{ $assignment->start_date?->format('d M Y') }} — {{ $assignment->end_date?->format('d M Y') ?? 'sekarang' }}</div></div>
                    <span class="badge align-self-start {{ $assignment->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $assignment->status === 'active' ? 'Aktif' : 'Selesai' }}</span>
                  </div>
                @endforeach
              </div>
            @else
              @foreach($group as $assignment)
                <div class="px-3 py-2 border-bottom d-flex justify-content-between gap-3">
                  <div><div class="fw-semibold">{{ $assignment->assignee_name }}</div><div class="small text-secondary">{{ $assignment->position?->name }} · {{ $assignment->start_date?->format('d M Y') }} — {{ $assignment->end_date?->format('d M Y') ?? 'sekarang' }}</div></div>
                  <span class="badge {{ $assignment->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $assignment->status === 'active' ? 'Aktif' : 'Selesai' }}</span>
                </div>
              @endforeach
            @endif
          @empty
            <div class="empty-state py-4">Belum ada penugasan pada unit ini.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">Riwayat unit</div>
        <div class="card-body p-0">
          @forelse($unit->histories as $history)
            <div class="px-3 py-3 border-bottom d-flex justify-content-between gap-3">
              <div><div class="fw-semibold">{{ $history->summary }}</div><div class="small text-secondary">{{ $history->actor?->name ?? 'Sistem' }}</div></div>
              <span class="small text-secondary text-nowrap">{{ $history->created_at?->translatedFormat('d M Y H:i') }}</span>
            </div>
          @empty
            <div class="empty-state py-4">Belum ada riwayat perubahan.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
