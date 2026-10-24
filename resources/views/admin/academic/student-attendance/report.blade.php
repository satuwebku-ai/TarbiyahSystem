@extends('admin._layout')
@section('title', 'Rekap Absensi Siswa')
@section('parent', 'Pendidikan')
@section('actions')
  <a href="{{ route('admin.academic.student-attendance.index') }}" class="btn btn-primary btn-sm">Catat absensi</a>
@endsection
@section('content')
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.academic.student-attendance.report') }}" class="row g-2 align-items-end" data-ajax-filter data-ajax-nopush>
        <div class="col-md-2">
          <label for="period" class="form-label small mb-1">Periode</label>
          <select id="period" name="period" class="form-select">
            <option value="day" @selected($period === 'day')>Harian</option>
            <option value="month" @selected($period === 'month')>Bulanan</option>
            <option value="year" @selected($period === 'year')>Tahun ajaran</option>
          </select>
        </div>
        <div class="col-md-2" id="date-filter" @if($period !== 'day') hidden @endif>
          <label for="date" class="form-label small mb-1">Tanggal</label>
          <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="form-control">
        </div>
        <div class="col-md-2" id="month-filter" @if($period !== 'month') hidden @endif>
          <label for="month" class="form-label small mb-1">Bulan</label>
          <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control">
        </div>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $selectedKomwil, 'komda' => $selectedKomda, 'school' => request('school_id')],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-6'],
        ])
        <div class="col-md-3">
          <label for="academic_year_id" class="form-label small mb-1">Tahun ajaran</label>
          <select id="academic_year_id" name="academic_year_id" class="form-select">
            <option value="">Semua tahun ajaran</option>
            @foreach($years as $year)
              <option value="{{ $year->id }}" data-school-id="{{ $year->school_id }}" @selected((string) request('academic_year_id') === (string) $year->id)>{{ $year->school?->unit?->name }} · {{ $year->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label for="classroom_id" class="form-label small mb-1">Kelas</label>
          <select id="classroom_id" name="classroom_id" class="form-select">
            <option value="">Semua kelas dalam cakupan</option>
            @foreach($classrooms as $classroom)
              <option value="{{ $classroom->id }}" data-school-id="{{ $classroom->school_id }}" data-year-id="{{ $classroom->academic_year_id }}" @selected((string) request('classroom_id') === (string) $classroom->id)>{{ $classroom->school?->unit?->name }} · {{ $classroom->name }} · {{ $classroom->academicYear?->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Tampilkan</button>
          <a href="{{ route('admin.academic.student-attendance.report') }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div data-ajax-region>
  @fragment('table')
  @if($period === 'year' && ! $selectedAcademicYear)
    <div class="alert alert-info">Pilih tahun ajaran untuk melihat rekap sepanjang tahun tersebut.</div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Catatan</div><div class="fs-4 fw-semibold">{{ $counts->total_records ?? 0 }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Sesi tercatat</div><div class="fs-4 fw-semibold">{{ $counts->total_sessions ?? 0 }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Hadir</div><div class="fs-4 fw-semibold text-success">{{ $counts->present ?? 0 }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Terlambat</div><div class="fs-4 fw-semibold text-warning">{{ $counts->late ?? 0 }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Izin / sakit</div><div class="fs-4 fw-semibold text-primary">{{ ($counts->permit ?? 0) + ($counts->sick ?? 0) }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-secondary">Alpa</div><div class="fs-4 fw-semibold text-danger">{{ $counts->absent ?? 0 }}</div></div></div></div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
      Rekap {{ $period === 'year' ? ($selectedAcademicYear?->name ?? 'Tahun ajaran') : ($period === 'month' ? $from->format('F Y') : $from->format('d M Y')) }}
      <span class="small text-secondary fw-normal">· dihitung per catatan sesi</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr><th>Sekolah</th><th>Tahun ajaran</th><th>Kelas</th><th>Siswa</th><th class="text-end">Sesi</th><th class="text-end">Hadir</th><th class="text-end">Terlambat</th><th class="text-end">Izin</th><th class="text-end">Sakit</th><th class="text-end">Alpa</th></tr>
        </thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td>{{ $row->school_name }}</td>
              <td>{{ $row->academic_year_name }}</td>
              <td>{{ $row->classroom_name }}</td>
              <td><div class="fw-semibold">{{ $row->student_name }}</div><div class="small text-secondary">NIS {{ $row->nis }}</div></td>
              <td class="text-end">{{ $row->total_sessions }}</td>
              <td class="text-end">{{ $row->present }}</td>
              <td class="text-end">{{ $row->late }}</td>
              <td class="text-end">{{ $row->permit }}</td>
              <td class="text-end">{{ $row->sick }}</td>
              <td class="text-end">{{ $row->absent }}</td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-secondary py-5">Belum ada catatan absensi pada filter ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($rows->hasPages()) <div class="card-footer bg-transparent">{{ $rows->links() }}</div> @endif
  </div>
  @endfragment
  </div>

  <script>
    (() => {
      const period = document.getElementById('period');
      const dateFilter = document.getElementById('date-filter');
      const monthFilter = document.getElementById('month-filter');
      const sync = () => {
        dateFilter.hidden = period.value !== 'day';
        monthFilter.hidden = period.value !== 'month';
      };
      period.addEventListener('change', sync);
      sync();
    })();
  </script>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
    @include('partials.admin.ajax-table')
  @endpush
@endsection
