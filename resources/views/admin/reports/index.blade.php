@extends('admin._layout')
@section('title', 'Dashboard & Laporan')
@section('parent', 'Laporan')
@section('content')
  @include('admin.reports._filter', ['action' => route('admin.reports.index'), 'usesPeriod' => true])

  <div data-ajax-region>
  @fragment('table')
  @php
    $n = fn ($v) => number_format((int) $v, 0, ',', '.');
    $pct = fn ($v) => $v === null ? '–' : number_format((float) $v, 1, ',', '.').'%';
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $q = request()->query();

    $cards = [];
    if (isset($summaries['employees'])) {
      $t = $summaries['employees']['totals'] ?? [];
      $cards[] = ['employees', 'Pegawai & guru', $n($t['total'] ?? 0), $n($t['teacher'] ?? 0).' guru · '.$n($t['staff'] ?? 0).' staf'.(max(0, ($t['total'] ?? 0) - ($t['teacher'] ?? 0) - ($t['staff'] ?? 0)) > 0 ? ' · '.$n(max(0, ($t['total'] ?? 0) - ($t['teacher'] ?? 0) - ($t['staff'] ?? 0))).' PB/lainnya' : ''), 'card-stat-blue'];
    }
    if (isset($summaries['employee-attendance'])) {
      $t = $summaries['employee-attendance']['totals'] ?? [];
      $cards[] = ['employee-attendance', 'Kehadiran pegawai', $pct($t['rate'] ?? null), $n($t['total'] ?? 0).' catatan absensi', 'card-stat-emerald'];
    }
    if (isset($summaries['programs'])) {
      $t = $summaries['programs']['totals'] ?? [];
      $cards[] = ['programs', 'Program kerja', $n($t['programs'] ?? 0), $n($t['activities_done'] ?? 0).' dari '.$n($t['activities'] ?? 0).' kegiatan selesai', 'card-stat-amber'];
    }
    if (isset($summaries['budget'])) {
      $t = $summaries['budget']['totals'] ?? [];
      $cards[] = ['budget', 'Realisasi anggaran', $pct($t['usage'] ?? null), $rp($t['realized'] ?? 0).' dari '.$rp($t['planned'] ?? 0), 'card-stat-rose'];
    }
    if (isset($summaries['schools'])) {
      $t = $summaries['schools']['totals'] ?? [];
      $cards[] = ['schools', 'Siswa aktif', $n($t['students_active'] ?? 0), $n(count($summaries['schools']['rows'])).' sekolah · '.$n($t['classrooms'] ?? 0).' kelas', 'card-stat-blue'];
    }
    if (isset($summaries['student-attendance'])) {
      $t = $summaries['student-attendance']['totals'] ?? [];
      $cards[] = ['student-attendance', 'Kehadiran siswa', $pct($t['rate'] ?? null), $n($t['total'] ?? 0).' catatan absensi', 'card-stat-emerald'];
    }
  @endphp

  @if($cards === [])
    <div class="alert alert-info">Akun Anda belum memiliki izin untuk melihat modul yang dilaporkan. Hubungi administrator.</div>
  @endif

  <div class="row g-3 mb-4">
    @foreach($cards as [$type, $label, $value, $sub, $cls])
      <div class="col-sm-6 col-xl-4">
        <a href="{{ route('admin.reports.show', ['report' => $type] + $q) }}" class="text-decoration-none text-reset">
          <div class="card {{ $cls }} border shadow-sm h-100">
            <div class="card-body">
              <div class="text-secondary small">{{ $label }}</div>
              <div class="fs-3 fw-bold lh-1 my-1">{{ $value }}</div>
              <div class="small text-secondary">{{ $sub }}</div>
            </div>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  @php
    $distributions = [];
    if (isset($summaries['employee-attendance']) && ($summaries['employee-attendance']['totals']['total'] ?? 0) > 0) {
      $t = $summaries['employee-attendance']['totals'];
      $distributions[] = ['Komposisi absensi pegawai', $t['total'], [
        ['Hadir', $t['present'], 'bg-success'], ['Terlambat', $t['late'], 'bg-warning'], ['Izin', $t['permit'], 'bg-primary'],
        ['Sakit', $t['sick'], 'bg-info'], ['Cuti', $t['leave_count'], 'bg-secondary'], ['Tidak hadir', $t['absent'], 'bg-danger'],
      ]];
    }
    if (isset($summaries['student-attendance']) && ($summaries['student-attendance']['totals']['total'] ?? 0) > 0) {
      $t = $summaries['student-attendance']['totals'];
      $distributions[] = ['Komposisi absensi siswa', $t['total'], [
        ['Hadir', $t['present'], 'bg-success'], ['Terlambat', $t['late'], 'bg-warning'], ['Izin', $t['permit'], 'bg-primary'],
        ['Sakit', $t['sick'], 'bg-info'], ['Alpa', $t['absent'], 'bg-danger'],
      ]];
    }
    if (isset($summaries['budget']) && ($summaries['budget']['totals']['planned'] ?? 0) > 0) {
      $t = $summaries['budget']['totals'];
      $distributions[] = ['Realisasi terhadap rencana anggaran', $t['planned'], [
        ['Terealisasi', min($t['realized'], $t['planned']), 'bg-success'],
        ['Sisa rencana', max($t['planned'] - $t['realized'], 0), 'bg-secondary'],
      ]];
    }
  @endphp

  @if($distributions !== [])
    <div class="row g-3 mb-4">
      @foreach($distributions as [$title, $total, $parts])
        <div class="col-lg-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold">{{ $title }}</div>
            <div class="card-body">
              <div class="progress mb-3" style="height: 18px" role="img" aria-label="{{ $title }}">
                @foreach($parts as [$label, $value, $bg])
                  @if($value > 0)
                    <div class="progress-bar {{ $bg }}" style="width: {{ round($value / $total * 100, 2) }}%" title="{{ $label }}"></div>
                  @endif
                @endforeach
              </div>
              <div class="d-flex flex-wrap gap-3 small">
                @foreach($parts as [$label, $value, $bg])
                  <span><span class="badge {{ $bg }} me-1">&nbsp;</span>{{ $label }}: <strong>{{ number_format($value, 0, ',', '.') }}</strong></span>
                @endforeach
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  @if($types !== [])
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent fw-semibold">Semua laporan</div>
      <div class="list-group list-group-flush">
        @foreach($types as $type => $definition)
          <a class="list-group-item list-group-item-action" href="{{ route('admin.reports.show', ['report' => $type] + $q) }}">
            <div class="fw-semibold">{{ $definition['title'] }}</div>
            <div class="small text-secondary">{{ $definition['description'] }}</div>
          </a>
        @endforeach
      </div>
    </div>
  @endif
  @endfragment
  </div>
@endsection
