@extends('admin._layout')
@section('title', 'Kebijakan Absensi')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('actions')<a href="{{ route('admin.attendance.policies.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah kebijakan</a>@endsection
@section('content')
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.policies.index') }}" class="row g-2 align-items-end" id="policyFilter">
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Tampilkan</button><a href="{{ route('admin.attendance.policies.index') }}" class="btn btn-light" data-dt-reset>Reset</a></div>
        <div class="col-12 form-text mt-1">Dengan filter wilayah/sekolah, daftar memuat kebijakan umum serta kebijakan yang berlaku pada unit terpilih.</div>
      </form>
    </div>
  </div>
  <div class="alert alert-light border small">Unit memakai kebijakan miliknya sendiri; jika tidak ada, naik ke unit induk, lalu ke kebijakan umum. Zona waktu (WIB/WITA/WIT) ikut kebijakan yang berlaku. Tanpa kebijakan yang berlaku, pegawai tidak bisa check-in.</div>
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblPolicies',
      'url' => route('admin.attendance.policies.index'),
      'filter' => '#policyFilter',
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada kebijakan absensi.',
      'columns' => [
        ['data' => 'name', 'title' => 'Nama'],
        ['data' => 'unit_name', 'title' => 'Berlaku untuk'],
        ['data' => 'days', 'title' => 'Hari kerja', 'orderable' => false, 'searchable' => false],
        ['data' => 'hours', 'title' => 'Jam', 'orderable' => false, 'searchable' => false],
        ['data' => 'tz', 'title' => 'Zona waktu', 'orderable' => false, 'searchable' => false],
        ['data' => 'late_tolerance_minutes', 'title' => 'Toleransi', 'searchable' => false],
        ['data' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
  @endpush
@endsection
