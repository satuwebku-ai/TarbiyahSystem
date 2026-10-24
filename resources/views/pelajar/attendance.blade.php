@extends('pelajar._layout')
@section('title', 'Absensi')
@section('title_visible', 'Rekap Absensi')
@section('subtitle', $student ? $student->name.' · NIS '.($student->nis ?? '—') : 'Kehadiran Anda per bulan')
@section('content')
  @if (! $student)
    <div class="alert alert-warning mb-0">
      Akun Anda belum tertaut dengan data siswa, sehingga rekap absensi belum bisa ditampilkan. Silakan hubungi admin sekolah.
    </div>
  @else
    <div class="card border-0 shadow-sm">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
          <a href="{{ route('pelajar.attendance', ['month' => $prevMonth]) }}" class="btn btn-light btn-sm" aria-label="Bulan sebelumnya">&laquo;</a>
          <div class="fw-semibold px-2">{{ $monthLabel }}</div>
          @if ($nextMonth)
            <a href="{{ route('pelajar.attendance', ['month' => $nextMonth]) }}" class="btn btn-light btn-sm" aria-label="Bulan berikutnya">&raquo;</a>
          @else
            <span class="btn btn-light btn-sm disabled" aria-disabled="true">&raquo;</span>
          @endif
        </div>
        <form method="GET" action="{{ route('pelajar.attendance') }}" class="d-flex align-items-center gap-2">
          <label for="month" class="visually-hidden">Pilih bulan</label>
          <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ $currentMonth }}" class="form-control form-control-sm">
          <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        </form>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body text-center">
            <div class="text-secondary small">Persentase hadir</div>
            <div class="fs-3 fw-bold">{{ $percentage !== null ? rtrim(rtrim(number_format($percentage, 1, ',', '.'), '0'), ',').'%' : '—' }}</div>
            <div class="text-secondary" style="font-size:.75rem">Hadir + terlambat</div>
          </div>
        </div>
      </div>
      @foreach ($statuses as $status)
        <div class="col-6 col-md-4 col-lg">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
              <div class="text-secondary small">{{ $status->label() }}</div>
              <div class="fs-3 fw-bold">{{ $counts[$status->value] }}</div>
              <span class="badge {{ $status->badge() }}">{{ $status->label() }}</span>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">Riwayat {{ $monthLabel }} <span class="text-secondary fw-normal small">({{ $total }} catatan)</span></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr><th>Tanggal</th><th>Kelas</th><th>Sesi</th><th>Status</th><th>Jam masuk</th><th>Keterangan</th></tr>
          </thead>
          <tbody>
            @forelse ($records as $record)
              <tr>
                <td class="text-nowrap">{{ $record->session->attendance_date->format('d M Y') }}</td>
                <td>{{ $record->session->classroom?->name ?? '—' }}</td>
                <td>{{ $record->session->name }}</td>
                <td><span class="badge {{ $record->status->badge() }}">{{ $record->status->label() }}</span></td>
                <td>{{ $record->checked_in_at?->format('H:i') ?? '—' }}</td>
                <td>{{ $record->notes ?: '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada data absensi pada bulan ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
