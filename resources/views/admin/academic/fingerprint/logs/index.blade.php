@extends('admin._layout')
@section('title', 'Log Fingerprint')
@section('parent', 'Absensi Siswa')
@section('parent_url', route('admin.academic.student-attendance.index'))
@section('actions')
  @if($canManage)
    <form method="POST" action="{{ route('admin.academic.fingerprint.logs.reprocess', $filters) }}">@csrf
      <button class="btn btn-outline-primary btn-sm" type="submit" title="Proses ulang log yang belum menjadi absensi (sesuai filter)">Proses ulang</button>
    </form>
  @endif
@endsection
@section('content')
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="GET" id="logFilter" action="{{ route('admin.academic.fingerprint.logs.index') }}" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1" for="school_id">Sekolah</label>
          <select id="school_id" name="school_id" class="form-select form-select-sm"><option value="">Semua</option>
            @foreach($schools as $s)<option value="{{ $s->id }}" @selected(($filters['school_id'] ?? '') == $s->id)>{{ $s->unit?->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="device_id">Mesin</label>
          <select id="device_id" name="device_id" class="form-select form-select-sm"><option value="">Semua</option>
            @foreach($devices as $d)<option value="{{ $d->id }}" @selected(($filters['device_id'] ?? '') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="result">Hasil</label>
          <select id="result" name="result" class="form-select form-select-sm"><option value="">Semua</option>
            @foreach($results as $key => $label)<option value="{{ $key }}" @selected(($filters['result'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-1"><label class="form-label small mb-1" for="pin">PIN</label><input id="pin" name="pin" value="{{ $filters['pin'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="date_from">Dari</label><input id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="date_to">Sampai</label><input id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm" type="submit">Tampilkan</button><a href="{{ route('admin.academic.fingerprint.logs.index') }}" class="btn btn-light btn-sm" data-dt-reset>Reset</a></div>
      </form>
    </div>
  </div>

  @if($unmapped->isNotEmpty())
    <div class="alert alert-warning mb-0">
      <div class="fw-semibold mb-1">PIN yang mengetuk tetapi belum terpetakan ke siswa</div>
      <div class="small">
        @foreach($unmapped as $row)
          <span class="badge text-bg-light border me-1">PIN {{ $row->pin }} · {{ $row->total }}×</span>
        @endforeach
      </div>
      @if($canManage)<div class="small mt-2">Isi PIN tersebut di <a href="{{ route('admin.academic.fingerprint.pins.index') }}">Pemetaan PIN</a>; log lama otomatis diproses ulang.</div>@endif
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblFingerprintLogs',
      'url' => route('admin.academic.fingerprint.logs.index'),
      'filter' => '#logFilter',
      'order' => [[0, 'desc']],
      'length' => 50,
      'empty' => 'Belum ada log ketukan.',
      'columns' => [
        ['data' => 'punched_at', 'title' => 'Waktu ketuk', 'searchable' => false],
        ['data' => 'pin', 'title' => 'PIN'],
        ['data' => 'student_name', 'title' => 'Siswa'],
        ['data' => 'device_name', 'title' => 'Mesin'],
        ['data' => 'result', 'title' => 'Hasil', 'searchable' => false],
      ],
    ])
  </div>
@endsection
