@extends('admin._layout')
@section('title', 'Izin / Sakit / Cuti')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('content')
  @include('partials.admin.select2-assets')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.entries.index') }}" class="row g-2 align-items-end" id="entries-filter" data-ajax-filter>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-auto d-flex gap-2"><button class="btn btn-outline-primary" type="submit">Saring riwayat</button><a href="{{ route('admin.attendance.entries.index') }}" class="btn btn-light">Reset</a></div>
        <div class="col-12 form-text mt-1">Pilihan KOMWIL → KOMDA → Sekolah juga mempersempit daftar pegawai pada formulir di bawah.</div>
      </form>
    </div>
  </div>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">Catat untuk rentang tanggal</div>
    <div class="card-body">
      <form method="POST" action="{{ route('admin.attendance.entries.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-3"><label class="form-label small mb-1" for="employee_id">Pegawai</label>
          <select id="employee_id" name="employee_id" class="form-select" required>
            <option value="">Cari atau pilih pegawai</option>
            @foreach($employees as $e)
              @php($a = $employeeAttrs[$e->id] ?? ['komwil' => [], 'komda' => [], 'school' => []])
              <option value="{{ $e->id }}" data-komwil-id="{{ implode(',', $a['komwil']) }}" data-komda-id="{{ implode(',', $a['komda']) }}" data-school-id="{{ implode(',', $a['school']) }}" @selected(old('employee_id') == $e->id)>{{ $e->name }} · {{ $e->employee_number }}</option>
            @endforeach
          </select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="e_status">Status</label>
          <select id="e_status" name="status" class="form-select" required>@foreach($statuses as $s)<option value="{{ $s->value }}" @selected(old('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="e_from">Dari</label><input id="e_from" type="date" name="date_from" value="{{ old('date_from') }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label small mb-1" for="e_to">Sampai</label><input id="e_to" type="date" name="date_to" value="{{ old('date_to') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="e_notes">Catatan</label><input id="e_notes" name="notes" value="{{ old('notes') }}" class="form-control" maxlength="500"></div>
        <div class="col-12 d-flex justify-content-between align-items-center">
          <span class="form-text">Hari libur dan hari non-kerja dilewati otomatis. Gagal jika sudah ada catatan di rentang itu.</span>
          <button class="btn btn-primary" type="submit">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <div data-ajax-region>
  @fragment('table')
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Tanggal</th><th>Pegawai</th><th>Status</th><th>Catatan</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($entries as $r)
            <tr>
              <td class="text-nowrap">{{ $r->attendance_date->format('d M Y') }}</td>
              <td><div class="fw-semibold">{{ $r->employee->name }}</div><div class="small text-secondary">{{ $r->unit?->name }}</div></td>
              <td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td>
              <td>{{ $r->notes ?: '—' }}</td>
              <td class="text-end"><form method="POST" action="{{ route('admin.attendance.entries.destroy', $r) }}" onsubmit="return confirm('Hapus catatan ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit"><x-icon name="trash" size="14"/></button></form></td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty-state py-5">Belum ada catatan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($entries->hasPages())<div class="card-footer bg-transparent">{{ $entries->links() }}</div>@endif
  </div>
  @endfragment
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
    @include('partials.admin.ajax-table')
  @endpush
@endsection
