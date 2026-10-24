@extends('pegawai._layout')
@section('title', 'Riwayat Absensi')
@section('title_visible', 'Riwayat Absensi')
@section('subtitle', 'Rekap bulanan milik Anda. Angka sama dengan rekap yang dilihat pengelola.')
@section('content')
  @php
    $local = fn ($t) => \App\Helpers\AttendanceClock::toLocal($t)?->format('H:i');
    $dur = fn ($m) => intdiv($m, 60).'j '.($m % 60).'m';
    $cols = ['present' => 'Hadir', 'late' => 'Terlambat', 'permit' => 'Izin', 'sick' => 'Sakit', 'leave' => 'Cuti', 'absent' => 'Tidak hadir'];
  @endphp

  <form method="GET" action="{{ route('pegawai.history') }}" class="card border-0 shadow-sm">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
      <div>
        <label class="form-label small mb-1" for="month">Bulan</label>
        <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ $maxMonth->format('Y-m') }}" class="form-control @error('month') is-invalid @enderror">
        @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <button class="btn btn-primary" type="submit">Tampilkan</button>
      <a href="{{ route('pegawai.history') }}" class="btn btn-light">Bulan ini</a>
    </div>
  </form>

  @if(! $row)
    <div class="card border-0 shadow-sm"><div class="card-body empty-state py-5">Tidak ada data penempatan atau absensi pada {{ $month->format('M Y') }}.</div></div>
  @else
    <div class="row g-2">
      @foreach($cols as $k => $label)
        <div class="col-6 col-md-4 col-lg-2"><div class="card border-0 shadow-sm text-center"><div class="card-body py-3"><div class="fs-4 fw-bold">{{ $row['counts'][$k] }}</div><div class="small text-secondary">{{ $label }}</div></div></div></div>
      @endforeach
    </div>
    <div class="small text-secondary">Jam kerja: <strong>{{ $dur($row['work_minutes']) }}</strong> · Menit terlambat: <strong>{{ $row['late_minutes'] }}</strong></div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">{{ $month->format('M Y') }}</div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Tanggal</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Terlambat</th></tr></thead>
          <tbody>
            @foreach($row['days'] as $date => $day)
              @php $r = $day['record']; @endphp
              <tr>
                <td>{{ \Carbon\Carbon::parse($date)->format('D, d M Y') }}</td>
                <td><span class="badge {{ \App\Services\AttendanceRecapService::badge($day['status']) }}">{{ \App\Services\AttendanceRecapService::label($day['status']) }}</span></td>
                <td>{{ $local($r?->check_in_at) ?? '—' }}</td>
                <td>{{ $local($r?->check_out_at) ?? '—' }}</td>
                <td>{{ $r?->late_minutes ? $r->late_minutes.' menit' : '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-transparent small text-secondary">“Tidak hadir” mencakup hari kerja yang sudah lewat tanpa catatan apa pun. Keliru? Ajukan koreksi di halaman Absen (maksimal {{ config('attendance.correction_window_days') }} hari ke belakang).</div>
    </div>
  @endif
  @include('partials.admin.clean-url')
@endsection
