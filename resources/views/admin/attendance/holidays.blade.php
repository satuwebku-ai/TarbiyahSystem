@extends('admin._layout')
@section('title', 'Hari Libur')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('content')
  @include('partials.admin.select2-assets')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.attendance.holidays.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-3"><label class="form-label small mb-1" for="h_date">Tanggal</label><input id="h_date" type="date" name="holiday_date" value="{{ old('holiday_date') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label small mb-1" for="h_name">Nama</label><input id="h_name" name="name" value="{{ old('name') }}" class="form-control" maxlength="150" required></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="h_unit">Berlaku untuk</label>
          <select id="h_unit" name="organizational_unit_id" class="form-select js-unit-select" data-placeholder="Seluruh organisasi">
            @if($canGlobal)<option value="">Seluruh organisasi</option>@endif
            @foreach($unitOptions as $o)
              <option value="{{ $o['id'] }}" data-name="{{ $o['name'] }}" data-code="{{ $o['code'] }}" data-depth="{{ $o['depth'] }}" data-path="{{ $o['path'] }}" @selected(old('organizational_unit_id') == $o['id'])>{{ $o['name'] }} · {{ $o['code'] }} (+ turunan)</option>
            @endforeach
          </select></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Tambah</button></div>
      </form>
    </div>
  </div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.holidays.index') }}" class="row g-2 align-items-end" id="holidayFilter">
        <div class="col-md-2"><label class="form-label small mb-1" for="year">Tahun</label><input id="year" type="number" name="year" value="{{ $year }}" class="form-control"></div>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Tampilkan</button><a href="{{ route('admin.attendance.holidays.index') }}" class="btn btn-light" data-dt-reset>Reset</a></div>
        <div class="col-12 form-text mt-1">Dengan filter wilayah/sekolah, daftar memuat libur umum serta libur yang berlaku pada unit terpilih.</div>
      </form>
    </div>
  </div>
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblHolidays',
      'url' => route('admin.attendance.holidays.index'),
      'filter' => '#holidayFilter',
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada hari libur pada tahun ini.',
      'columns' => [
        ['data' => 'holiday_date', 'title' => 'Tanggal', 'searchable' => false],
        ['data' => 'name', 'title' => 'Nama'],
        ['data' => 'unit_name', 'title' => 'Berlaku untuk'],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end', 'headClass' => 'text-end'],
      ],
    ])
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
  @endpush
@endsection
