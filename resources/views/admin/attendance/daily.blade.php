@extends('admin._layout')
@section('title', 'Rekap Absensi Harian')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.monthly'))
@section('content')
  @include('admin.attendance.partials.filter', ['action' => route('admin.attendance.daily'), 'periodLabel' => 'Tanggal', 'periodType' => 'date', 'periodName' => 'date', 'periodValue' => $date->toDateString()])
  <div data-ajax-region>
  @fragment('table')
  @if($truncated)<div class="alert alert-warning">Hanya 500 pegawai pertama yang ditampilkan. Persempit dengan filter KOMWIL / KOMDA / sekolah.</div>@endif
  @php
    $local = fn ($t, $tz) => ($l = \App\Helpers\AttendanceClock::toLocal($t, $tz)) ? $l->format('H:i').' '.\App\Helpers\AttendanceClock::abbr($tz) : null;
    $dur = fn ($m) => $m === null ? '—' : intdiv($m, 60).'j '.($m % 60).'m';
    $key = $date->toDateString();
  @endphp
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Pegawai</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Terlambat</th><th>Durasi</th><th>Sumber</th><th>Bukti</th></tr></thead>
        <tbody>
          @forelse($rows as $row)
            @php($day = $row['days'][$key] ?? null)
            @continue(! $day)
            @php($rec = $day['record'])
            <tr>
              <td><a href="{{ route('admin.employees.show', $row['employee']) }}" class="fw-semibold text-decoration-none">{{ $row['employee']->name }}</a><div class="small text-secondary">{{ $row['employee']->employee_number }}</div></td>
              <td><span class="badge {{ \App\Services\AttendanceRecapService::badge($day['status']) }}">{{ \App\Services\AttendanceRecapService::label($day['status']) }}</span></td>
              <td>{{ $local($rec?->check_in_at, $day['tz']) ?? '—' }}</td>
              <td>{{ $local($rec?->check_out_at, $day['tz']) ?? '—' }}</td>
              <td>{{ $rec?->late_minutes ? $rec->late_minutes.' menit' : '—' }}</td>
              <td>{{ $dur($rec?->work_minutes) }}</td>
              <td class="small text-secondary">{{ $rec ? ['web' => 'Check-in', 'correction' => 'Koreksi', 'manual' => 'Dicatat pengelola'][$rec->source] ?? $rec->source : '—' }}</td>
              <td class="small text-nowrap">
                @if($rec?->check_in_photo)<a href="{{ route('admin.attendance.photo', [$rec, 'in']) }}" target="_blank" rel="noopener" class="text-decoration-none">Foto masuk</a>@endif
                @if($rec?->check_out_photo)<a href="{{ route('admin.attendance.photo', [$rec, 'out']) }}" target="_blank" rel="noopener" class="text-decoration-none ms-2">Foto pulang</a>@endif
                @if($rec?->check_in_lat !== null)<div><a href="https://www.google.com/maps?q={{ $rec->check_in_lat }},{{ $rec->check_in_lng }}" target="_blank" rel="noopener" class="text-decoration-none">Lokasi masuk</a>@if($rec->check_in_distance_m !== null)<span class="text-secondary"> · {{ $rec->check_in_distance_m }} m dari sekolah</span>@endif</div>@endif
                @if($rec)@foreach($rec->faceBadges() as $b)<div><span class="badge text-bg-{{ $b['class'] }}">{{ $b['text'] }}</span></div>@endforeach @endif
                @if($rec && ($rb = $rec->reviewBadge()))<div><span class="badge text-bg-{{ $rb['class'] }}" title="{{ $rb['title'] }}">{{ $rb['text'] }}</span></div>@endif
                @if($rec && ! $rec->photo_review_status && ($notes = $rec->photoNotes()))<div><span class="badge text-bg-warning" title="{{ implode('; ', $notes) }}">⚠ Foto perlu dicek</span></div>@endif
                @if(! $rec?->check_in_photo && ! $rec?->check_out_photo && $rec?->check_in_lat === null)<span class="text-secondary">—</span>@endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="empty-state py-5">Tidak ada pegawai bertugas pada tanggal ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endfragment
  </div>
@endsection
