@extends('admin._layout')
@section('title', 'Koreksi Absensi')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('content')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  @include('partials.admin.select2-assets')
  @php($orgQuery = array_filter(['komwil_id' => $filters['komwil'], 'komda_id' => $filters['komda'], 'school_id' => $filters['school']]))
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.corrections.index') }}" class="row g-2 align-items-end" data-ajax-filter>
        <input type="hidden" name="status" value="{{ $status->value }}" data-ajax-sync>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Terapkan</button><a href="{{ route('admin.attendance.corrections.index', ['status' => $status->value]) }}" class="btn btn-light">Reset</a></div>
      </form>
    </div>
  </div>
  <div data-ajax-region>
  @fragment('table')
  <ul class="nav nav-pills mb-3">
    @foreach(\App\Enums\CorrectionStatus::cases() as $s)
      <li class="nav-item"><a data-ajax class="nav-link {{ $status === $s ? 'active' : '' }}" href="{{ route('admin.attendance.corrections.index', ['status' => $s->value] + $orgQuery) }}">{{ $s->label() }}</a></li>
    @endforeach
  </ul>
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Pegawai</th><th>Tanggal</th><th>Jam diajukan</th><th>Alasan</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($corrections as $c)
            <tr>
              <td><div class="fw-semibold">{{ $c->employee->name }}</div><div class="small text-secondary">{{ $c->employee->employee_number }}</div></td>
              <td class="text-nowrap">{{ $c->attendance_date->format('d M Y') }}</td>
              <td class="text-nowrap">{{ $c->requested_check_in ? substr($c->requested_check_in, 0, 5) : '—' }} – {{ $c->requested_check_out ? substr($c->requested_check_out, 0, 5) : '—' }}</td>
              <td>{{ $c->reason }}@if($c->review_note)<div class="small text-secondary">Catatan: {{ $c->review_note }} ({{ $c->reviewer?->name }})</div>@endif</td>
              <td class="text-end">
                @if($c->status === \App\Enums\CorrectionStatus::Pending)
                  <form method="POST" action="{{ route('admin.attendance.corrections.approve', $c) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-success" type="submit">Setujui</button></form>
                  <form method="POST" action="{{ route('admin.attendance.corrections.reject', $c) }}" class="d-inline-flex gap-1 mt-1">@csrf @method('PATCH')
                    <input name="review_note" class="form-control form-control-sm" placeholder="Alasan penolakan" maxlength="500" required aria-label="Alasan penolakan">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button>
                  </form>
                @else
                  <span class="badge {{ $c->status->badge() }}">{{ $c->status->label() }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty-state py-5">Tidak ada pengajuan {{ strtolower($status->label()) }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($corrections->hasPages())<div class="card-footer bg-transparent">{{ $corrections->links() }}</div>@endif
  </div>
  @endfragment
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
    @include('partials.admin.ajax-table')
  @endpush
@endsection
