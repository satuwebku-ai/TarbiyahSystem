@extends('admin._layout')
@section('title', 'Data Pegawai')
@section('actions')
  @can('employees.create')
    <a href="{{ route('admin.employees.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah pegawai</a>
  @endcan
@endsection
@section('content')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.employees.index') }}" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label for="q" class="form-label small mb-1">Cari nama / nomor pegawai</label>
          <input id="q" name="q" value="{{ $filters['q'] }}" class="form-control">
        </div>
        <div class="col-md-3">
          <label for="unit_id" class="form-label small mb-1">Unit</label>
          <select id="unit_id" name="unit_id" class="form-select">
            <option value="">Semua unit dalam cakupan</option>
            @foreach($units as $unit)<option value="{{ $unit->id }}" @selected($filters['unit_id'] == $unit->id)>{{ $unit->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label for="status" class="form-label small mb-1">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Semua status</option>
            @foreach($statuses as $s)<option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>@endforeach
          </select>
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Terapkan</button>
          <a href="{{ route('admin.employees.index') }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Pegawai</th><th>Jenis</th><th>Penempatan</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($employees as $employee)
            @php($current = $employee->assignments->first(fn ($a) => $a->end_date === null || $a->end_date->gte(today())))
            <tr>
              <td><div class="fw-semibold">{{ $employee->name }}</div><div class="small text-secondary">{{ $employee->employee_number }}</div></td>
              <td>{{ $employee->typeLabel() }}</td>
              <td>{{ $current?->unit?->name ?? '—' }}</td>
              <td><span class="badge {{ $employee->status->badge() }}">{{ $employee->status->label() }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                @can('employees.update')<a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>@endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty-state py-5">Belum ada pegawai pada cakupan Anda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($employees->hasPages()) <div class="card-footer bg-transparent">{{ $employees->links() }}</div> @endif
  </div>
@endsection
