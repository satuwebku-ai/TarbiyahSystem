@extends('admin._layout')
@section('title', 'Koreksi Absensi')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('content')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  @include('partials.admin.select2-assets')
  @php($orgQuery = array_filter(['komwil_id' => $filters['komwil'], 'komda_id' => $filters['komda'], 'school_id' => $filters['school']]))
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.corrections.index') }}" class="row g-2 align-items-end" id="correctionFilter">
        <input type="hidden" name="status" value="{{ $status->value }}">
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Terapkan</button><a href="{{ route('admin.attendance.corrections.index', ['status' => $status->value]) }}" class="btn btn-light" data-dt-reset>Reset</a></div>
      </form>
    </div>
  </div>
  <ul class="nav nav-pills mb-3">
    @foreach(\App\Enums\CorrectionStatus::cases() as $s)
      <li class="nav-item"><a data-dt-tab="status" data-value="{{ $s->value }}" class="nav-link {{ $status === $s ? 'active' : '' }}" href="{{ route('admin.attendance.corrections.index', ['status' => $s->value]) }}">{{ $s->label() }}</a></li>
    @endforeach
  </ul>
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblCorrections',
      'url' => route('admin.attendance.corrections.index'),
      'filter' => '#correctionFilter',
      'order' => [[1, 'asc']],
      'empty' => 'Tidak ada pengajuan pada status ini.',
      'columns' => [
        ['data' => 'employee_name', 'title' => 'Pegawai'],
        ['data' => 'attendance_date', 'title' => 'Tanggal', 'searchable' => false],
        ['data' => 'requested', 'title' => 'Jam diajukan', 'orderable' => false, 'searchable' => false],
        ['data' => 'reason', 'title' => 'Alasan'],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end', 'headClass' => 'text-end'],
      ],
    ])
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
  @endpush
@endsection
