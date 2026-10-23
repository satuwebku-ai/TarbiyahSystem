@extends('admin._layout')
@section('title', 'Rekap Absensi Bulanan')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('content')
  @include('admin.attendance.partials.filter', ['action' => route('admin.attendance.monthly'), 'periodLabel' => 'Bulan', 'periodType' => 'month', 'periodName' => 'month', 'periodValue' => $month->format('Y-m')])
  <div data-ajax-region>
  @fragment('table')
  @if($truncated)<div class="alert alert-warning">Hanya 500 pegawai pertama yang ditampilkan. Persempit dengan filter KOMWIL / KOMDA / sekolah.</div>@endif
  @php
    $dur = fn ($m) => intdiv($m, 60).'j '.($m % 60).'m';
    $cols = ['present' => 'Hadir', 'late' => 'Terlambat', 'permit' => 'Izin', 'sick' => 'Sakit', 'leave' => 'Cuti', 'absent' => 'Tidak hadir'];
  @endphp

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">Per unit</div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Unit</th>@foreach($cols as $label)<th class="text-end">{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
          @forelse($units as $u)
            <tr><td>{{ $u['unit'] }}</td>@foreach($cols as $k => $label)<td class="text-end">{{ $u['counts'][$k] }}</td>@endforeach</tr>
          @empty
            <tr><td colspan="7" class="empty-state py-4">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Per pegawai · {{ $month->format('M Y') }}</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Pegawai</th>@foreach($cols as $label)<th class="text-end">{{ $label }}</th>@endforeach<th class="text-end">Jam kerja</th><th class="text-end">Menit terlambat</th></tr></thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td><a href="{{ route('admin.employees.show', $row['employee']) }}" class="fw-semibold text-decoration-none">{{ $row['employee']->name }}</a><div class="small text-secondary">{{ $row['employee']->employee_number }}</div></td>
              @foreach($cols as $k => $label)<td class="text-end">{{ $row['counts'][$k] ?: '—' }}</td>@endforeach
              <td class="text-end">{{ $dur($row['work_minutes']) }}</td>
              <td class="text-end">{{ $row['late_minutes'] ?: '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="9" class="empty-state py-5">Tidak ada pegawai bertugas pada bulan ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent small text-secondary">“Tidak hadir” mencakup hari kerja yang sudah lewat tanpa catatan apa pun. Hari ini yang belum ada catatan belum dihitung.</div>
  </div>
  @endfragment
  </div>
@endsection
