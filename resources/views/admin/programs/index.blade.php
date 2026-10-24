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
      <form method="GET" id="programFilter" action="{{ route('admin.programs.index') }}" class="row g-2 align-items-end">
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
          <a href="{{ route('admin.programs.index') }}" class="btn btn-light" data-dt-reset>Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblPrograms',
      'url' => route('admin.programs.index'),
      'filter' => '#programFilter',
      'order' => [[2, 'desc']],
      'empty' => 'Belum ada program kerja pada cakupan Anda.',
      'columns' => [
        ['data' => 'title', 'title' => 'Program'],
        ['data' => 'unit_name', 'title' => 'Unit'],
        ['data' => 'start_date', 'title' => 'Periode', 'searchable' => false],
        ['data' => 'activities_count', 'title' => 'Kegiatan', 'searchable' => false],
        ['data' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Aksi', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
@endsection
