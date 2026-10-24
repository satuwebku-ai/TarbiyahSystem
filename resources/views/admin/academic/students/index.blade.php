@extends('admin._layout')
@section('title', 'Data Siswa')
@section('actions')
  @can('education.students.manage')
    <a href="{{ route('admin.academic.students.create') }}" class="btn btn-primary btn-sm">Tambah siswa</a>
  @endcan
@endsection
@section('content')
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" id="studentFilter" action="{{ route('admin.academic.students.index') }}" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label for="q" class="form-label small mb-1">Cari nama, NIS, atau NISN</label>
          <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control">
        </div>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil_id'] ?? '', 'komda' => $filters['komda_id'] ?? '', 'school' => $filters['school_id'] ?? ''],
          'cols' => ['komwil' => 'col-md-2', 'komda' => 'col-md-2', 'school' => 'col-md-3'],
        ])
        <div class="col-md-2">
          <label for="status" class="form-label small mb-1">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Semua</option>
            @foreach($statuses as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Cari</button>
          <a href="{{ route('admin.academic.students.index') }}" class="btn btn-light" data-dt-reset>Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblStudents',
      'url' => route('admin.academic.students.index'),
      'filter' => '#studentFilter',
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada siswa pada cakupan Anda.',
      'columns' => [
        ['data' => 'name', 'title' => 'Siswa'],
        ['data' => 'school_name', 'title' => 'Sekolah'],
        ['data' => 'level_name', 'title' => 'Jenjang'],
        ['data' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Aksi', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
  @endpush
@endsection
