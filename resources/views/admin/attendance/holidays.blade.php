@extends('admin._layout')
@section('title', 'Hari Libur')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('content')
  @include('partials.admin.select2-assets')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="POST" action="{{ route('admin.attendance.holidays.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-3"><label class="form-label small mb-1" for="h_date">Tanggal</label><input id="h_date" type="date" name="holiday_date" value="{{ old('holiday_date') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label small mb-1" for="h_name">Nama</label><input id="h_name" name="name" value="{{ old('name') }}" class="form-control" maxlength="150" required></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="h_unit">Berlaku untuk</label>
          <select id="h_unit" name="organizational_unit_id" class="form-select js-unit-select" data-placeholder="Seluruh organisasi">
            @if($canGlobal)<option value="">Seluruh organisasi</option>@endif
            @foreach($unitOptions as $o)
              <option value="{{ $o['id'] }}" data-name="{{ $o['name'] }}" data-code="{{ $o['code'] }}" data-depth="{{ $o['depth'] }}" data-path="{{ $o['path'] }}" @selected(old('organizational_unit_id') == $o['id'])>{{ $o['name'] }} · {{ $o['code'] }} (+ turunan)</option>
            @endforeach
          </select></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Tambah</button></div>
      </form>
    </div>
  </div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.holidays.index') }}" class="row g-2 align-items-end" data-ajax-filter>
        <div class="col-md-2"><label class="form-label small mb-1" for="year">Tahun</label><input id="year" type="number" name="year" value="{{ $year }}" class="form-control"></div>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Tampilkan</button><a href="{{ route('admin.attendance.holidays.index') }}" class="btn btn-light">Reset</a></div>
        <div class="col-12 form-text mt-1">Dengan filter wilayah/sekolah, daftar memuat libur umum serta libur yang berlaku pada unit terpilih.</div>
      </form>
    </div>
  </div>
  <div data-ajax-region>
  @fragment('table')
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Tanggal</th><th>Nama</th><th>Berlaku untuk</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($holidays as $h)
            <tr><td class="text-nowrap">{{ $h->holiday_date->format('d M Y') }}</td><td>{{ $h->name }}</td><td>{{ $h->unit?->name ?? 'Seluruh organisasi' }}</td>
              <td class="text-end"><form method="POST" action="{{ route('admin.attendance.holidays.destroy', $h) }}" onsubmit="return confirm('Hapus hari libur ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit"><x-icon name="trash" size="14"/></button></form></td></tr>
          @empty
            <tr><td colspan="4" class="empty-state py-5">Belum ada hari libur pada {{ $year }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @endfragment
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
    @include('partials.admin.ajax-table')
  @endpush
@endsection
