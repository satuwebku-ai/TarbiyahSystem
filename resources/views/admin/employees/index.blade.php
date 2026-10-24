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
      <form method="GET" id="employeeFilter" action="{{ route('admin.employees.index') }}" class="row g-2 align-items-end">
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
          <a href="{{ route('admin.employees.index') }}" class="btn btn-light" data-dt-reset>Reset</a>
        </div>
      </form>
    </div>
  </div>
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblEmployees',
      'url' => route('admin.employees.index'),
      'filter' => '#employeeFilter',
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada pegawai pada cakupan Anda.',
      'columns' => [
        ['data' => 'name', 'title' => 'Pegawai'],
        ['data' => 'employee_type', 'title' => 'Jenis', 'searchable' => false],
        ['data' => 'placement', 'title' => 'Penempatan', 'orderable' => false, 'searchable' => false],
        ['data' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
@endsection
