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
      <form method="GET" action="{{ route('admin.academic.fingerprint.logs.index') }}" class="row g-2 align-items-end">
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
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm" type="submit">Tampilkan</button><a href="{{ route('admin.academic.fingerprint.logs.index') }}" class="btn btn-light btn-sm">Reset</a></div>
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
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Waktu ketuk</th><th>PIN</th><th>Siswa</th><th>Mesin</th><th>Hasil</th></tr></thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td class="text-nowrap">{{ \App\Helpers\AttendanceClock::toLocal($log->punched_at)->format('d M Y H:i:s') }}</td>
              <td>{{ $log->pin }}</td>
              <td>@if($log->student)<div class="fw-semibold">{{ $log->student->name }}</div><div class="small text-secondary">NIS {{ $log->student->nis }}</div>@else<span class="text-secondary">—</span>@endif</td>
              <td>{{ $log->device?->name }}<div class="small text-secondary">{{ $log->school?->unit?->name }}</div></td>
              <td><span class="badge {{ $log->resultBadge() }}">{{ $log->resultLabel() }}</span>@if($log->note)<div class="small text-secondary">{{ $log->note }}</div>@endif</td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty-state py-5">Belum ada log ketukan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($logs->hasPages())<div class="card-footer bg-transparent">{{ $logs->links() }}</div>@endif
  </div>
@endsection
