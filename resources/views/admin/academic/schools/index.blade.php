@extends('admin._layout')
@section('title', 'Data Sekolah')
@section('actions')
  @can('education.schools.manage')
    <a href="{{ route('admin.academic.schools.create') }}" class="btn btn-primary btn-sm">Tambah sekolah</a>
  @endcan
@endsection
@section('content')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.academic.schools.index') }}" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label for="q" class="form-label small mb-1">Cari sekolah, kode, atau NPSN</label>
          <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-3">
          <label for="level_id" class="form-label small mb-1">Jenjang</label>
          <select id="level_id" name="level_id" class="form-select">
            <option value="">Semua jenjang</option>
            @foreach($levels as $level)
              <option value="{{ $level->id }}" @selected(($filters['level_id'] ?? '') == $level->id)>{{ $level->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label for="status" class="form-label small mb-1">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Semua</option>
            @foreach(\App\Models\School::STATUSES as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Cari</button>
          <a href="{{ route('admin.academic.schools.index') }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Sekolah</th><th>Jenjang</th><th>KOMDA</th>@if($canViewStudents)<th>Siswa</th>@endif<th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
          @forelse($schools as $school)
            <tr>
              <td>
                <div class="fw-semibold">{{ $school->unit?->name ?? '—' }}</div>
                <div class="small text-secondary">{{ $school->unit?->code }} · NPSN {{ $school->npsn ?: 'belum diisi' }}</div>
              </td>
              <td>{{ $school->level?->name ?? '—' }}</td>
              <td>{{ $school->unit?->parent?->name ?? '—' }}</td>
              @if($canViewStudents)<td>{{ number_format($school->students_count) }}</td>@endif
              <td><span class="badge text-bg-{{ $school->status === 'active' ? 'success' : 'secondary' }}">{{ \App\Models\School::STATUSES[$school->status] ?? $school->status }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.academic.schools.show', $school) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                @can('education.schools.manage')
                  <a href="{{ route('admin.academic.schools.edit', $school) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="{{ $canViewStudents ? 6 : 5 }}" class="text-center text-secondary py-5">Belum ada sekolah pada cakupan unit Anda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($schools->hasPages()) <div class="card-footer bg-transparent">{{ $schools->links() }}</div> @endif
  </div>
@endsection
