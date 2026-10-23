@extends('admin._layout')
@section('title', 'Kebijakan Absensi')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('actions')<a href="{{ route('admin.attendance.policies.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah kebijakan</a>@endsection
@section('content')
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.policies.index') }}" class="row g-2 align-items-end" data-ajax-filter>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Tampilkan</button><a href="{{ route('admin.attendance.policies.index') }}" class="btn btn-light">Reset</a></div>
        <div class="col-12 form-text mt-1">Dengan filter wilayah/sekolah, daftar memuat kebijakan umum serta kebijakan yang berlaku pada unit terpilih.</div>
      </form>
    </div>
  </div>
  <div class="alert alert-light border small">Unit memakai kebijakan miliknya sendiri; jika tidak ada, naik ke unit induk, lalu ke kebijakan umum. Tanpa kebijakan yang berlaku, pegawai tidak bisa check-in.</div>
  <div data-ajax-region>
  @fragment('table')
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Nama</th><th>Berlaku untuk</th><th>Hari kerja</th><th>Jam</th><th>Toleransi</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($policies as $p)
            <tr>
              <td class="fw-semibold">{{ $p->name }}</td>
              <td>{{ $p->unit?->name ?? 'Umum (seluruh organisasi)' }}</td>
              <td>{{ $p->dayLabels() }}</td>
              <td class="text-nowrap">{{ substr($p->work_start, 0, 5) }}–{{ substr($p->work_end, 0, 5) }}</td>
              <td>{{ $p->late_tolerance_minutes }} menit</td>
              <td><span class="badge {{ $p->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td class="text-end text-nowrap">
                @if($p->organizational_unit_id || $isUnrestricted)
                  <a href="{{ route('admin.attendance.policies.edit', $p) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                  <form method="POST" action="{{ route('admin.attendance.policies.toggle', $p) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty-state py-5">Belum ada kebijakan absensi.</td></tr>
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
