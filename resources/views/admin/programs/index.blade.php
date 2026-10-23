@extends('admin._layout')
@section('title', 'Program Kerja & Kegiatan')
@section('actions')
  @can('programs.manage')
    <a href="{{ route('admin.programs.create') }}" class="btn btn-primary btn-sm">Buat program</a>
  @endcan
@endsection
@section('content')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.programs.index') }}" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label for="q" class="form-label small mb-1">Cari program / kode</label>
          <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control">
        </div>
        <div class="col-md-3">
          <label for="unit_id" class="form-label small mb-1">Unit organisasi</label>
          <select id="unit_id" name="unit_id" class="form-select">
            <option value="">Semua unit dalam cakupan</option>
            @foreach($units as $unit)
              <option value="{{ $unit->id }}" @selected(($filters['unit_id'] ?? '') == $unit->id)>{{ $unit->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label for="status" class="form-label small mb-1">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Semua status</option>
            @foreach($statuses as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Terapkan</button>
          <a href="{{ route('admin.programs.index') }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr><th>Program</th><th>Unit</th><th>Periode</th><th>Kegiatan</th><th>Status</th><th class="text-end">Aksi</th></tr>
        </thead>
        <tbody>
          @forelse($programs as $program)
            <tr>
              <td>
                <div class="fw-semibold">{{ $program->title }}</div>
                <div class="small text-secondary">{{ $program->code }}</div>
              </td>
              <td>{{ $program->unit?->name ?? '—' }}</td>
              <td class="text-nowrap">{{ $program->start_date->format('d M Y') }} – {{ $program->end_date->format('d M Y') }}</td>
              <td>{{ $program->activities_count }}</td>
              <td><span class="badge text-bg-{{ $program->status === 'approved' ? 'success' : ($program->status === 'submitted' ? 'warning' : ($program->status === 'rejected' ? 'danger' : 'secondary')) }}">{{ \App\Models\WorkProgram::STATUSES[$program->status] ?? $program->status }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.programs.show', $program) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                @if(in_array($program->status, ['draft', 'rejected'], true))
                  @can('programs.manage')
                    <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                  @endcan
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-secondary py-5">Belum ada program kerja pada cakupan Anda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($programs->hasPages()) <div class="card-footer bg-transparent">{{ $programs->links() }}</div> @endif
  </div>
@endsection
