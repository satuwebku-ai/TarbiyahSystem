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
      <form method="GET" id="schoolFilter" action="{{ route('admin.academic.schools.index') }}" class="row g-2 align-items-end">
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
          <a href="{{ route('admin.academic.schools.index') }}" class="btn btn-light" data-dt-reset>Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    @php
      $cols = [
        ['data' => 'unit_name', 'title' => 'Sekolah'],
        ['data' => 'level_name', 'title' => 'Jenjang'],
        ['data' => 'komda_name', 'title' => 'KOMDA'],
      ];
      if ($canViewStudents) { $cols[] = ['data' => 'students_count', 'title' => 'Siswa', 'searchable' => false]; }
      $cols[] = ['data' => 'status', 'title' => 'Status', 'searchable' => false];
      $cols[] = ['data' => 'action', 'title' => 'Aksi', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'];
    @endphp
    @include('partials.admin.datatable', [
      'id' => 'tblSchools',
      'url' => route('admin.academic.schools.index'),
      'filter' => '#schoolFilter',
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada sekolah pada cakupan unit Anda.',
      'columns' => $cols,
    ])
  </div>
@endsection
