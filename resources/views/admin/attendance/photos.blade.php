@extends('admin._layout')
@section('title', 'Periksa Foto Absen')
@section('parent', 'Absensi Pegawai')
@section('parent_url', route('admin.attendance.daily'))
@section('content')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.attendance.photos.index') }}" class="row g-2 align-items-end">
        <input type="hidden" name="status" value="{{ $status }}">
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
          'allLabel' => 'Semua sekolah / unit dalam cakupan',
        ])
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Dari</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Sampai</label><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
        <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Terapkan</button><a href="{{ route('admin.attendance.photos.index', ['status' => $status]) }}" class="btn btn-light">Reset</a></div>
      </form>
    </div>
  </div>

  @php($query = array_filter(['komwil_id' => $filters['komwil'], 'komda_id' => $filters['komda'], 'school_id' => $filters['school'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]))
  <ul class="nav nav-pills mb-3">
    @foreach(\App\Http\Controllers\Admin\AttendancePhotoReviewController::STATUSES as $key => $label)
      <li class="nav-item"><a class="nav-link {{ $status === $key ? 'active' : '' }}" href="{{ route('admin.attendance.photos.index', ['status' => $key] + $query) }}">{{ $label }} <span class="badge text-bg-light">{{ $counts[$key] }}</span></a></li>
    @endforeach
  </ul>

  @if($status === 'perlu')
    <div class="small text-secondary mb-3">Menampilkan absen berfoto yang punya penanda (foto berciri galeri, atau wajah "perlu dicek / tidak cocok / tidak terdeteksi") dan belum diputuskan. Penanda hanya petunjuk: bandingkan wajah di foto dengan pegawainya, lalu putuskan.</div>
  @endif

  <div class="row g-3">
    @forelse($records as $rec)
      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-start gap-2">
            <div>
              <a href="{{ route('admin.employees.show', $rec->employee_id) }}" class="fw-semibold text-decoration-none">{{ $rec->employee?->name }}</a>
              <div class="small text-secondary">{{ $rec->employee?->employee_number }}</div>
            </div>
            <span class="small text-secondary text-nowrap">{{ $rec->attendance_date->format('d M Y') }}</span>
          </div>
          <div class="card-body">
            <div class="row g-2 mb-2">
              @foreach(['in' => ['Masuk', 'check_in_photo'], 'out' => ['Pulang', 'check_out_photo']] as $type => [$label, $col])
                <div class="col-6">
                  <div class="small text-secondary mb-1">{{ $label }}</div>
                  @if($rec->{$col})
                    <a href="{{ route('admin.attendance.photo', [$rec, $type]) }}" target="_blank" rel="noopener"><img loading="lazy" src="{{ route('admin.attendance.photo', [$rec, $type]) }}" alt="Foto {{ strtolower($label) }}" class="rounded border w-100" style="aspect-ratio:4/3;object-fit:cover"></a>
                  @else
                    <div class="rounded border d-flex align-items-center justify-content-center text-secondary small" style="aspect-ratio:4/3">Tidak ada foto</div>
                  @endif
                </div>
              @endforeach
            </div>
            @foreach($rec->faceBadges() as $b)<div><span class="badge text-bg-{{ $b['class'] }}">{{ $b['text'] }}</span></div>@endforeach
            @foreach($rec->photoNotes() as $note)<div class="small text-warning-emphasis">⚠ {{ $note }}</div>@endforeach
            @if($rb = $rec->reviewBadge())
              <div class="mt-2"><span class="badge text-bg-{{ $rb['class'] }}">{{ $rb['text'] }}</span>
                <span class="small text-secondary">oleh {{ $rec->photoReviewer?->name ?? '—' }}, {{ $rec->photo_reviewed_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</span>
                @if($rec->photo_review_note)<div class="small">Catatan: {{ $rec->photo_review_note }}</div>@endif
              </div>
            @endif
          </div>
          @can('attendance.photos.review')
            <div class="card-footer bg-transparent">
              @if($rec->photo_review_status)
                <form method="POST" action="{{ route('admin.attendance.photos.review', $rec) }}" class="d-inline">
                  @csrf<input type="hidden" name="decision" value="reset"><button class="btn btn-sm btn-outline-secondary" type="submit">Buka kembali</button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.attendance.photos.review', $rec) }}" class="d-flex flex-wrap gap-2">
                  @csrf
                  <input type="text" name="note" maxlength="500" class="form-control form-control-sm" placeholder="Catatan (wajib bila mencurigakan)">
                  <button class="btn btn-sm btn-success" type="submit" name="decision" value="ok">Sesuai</button>
                  <button class="btn btn-sm btn-danger" type="submit" name="decision" value="suspicious">Mencurigakan</button>
                </form>
              @endif
              @if($faceEnabled)
                <form method="POST" action="{{ route('admin.attendance.photos.rematch', $rec) }}" class="d-inline ms-1">
                  @csrf<button class="btn btn-sm btn-link text-decoration-none" type="submit">Cocokkan ulang wajah</button>
                </form>
              @endif
            </div>
          @endcan
        </div>
      </div>
    @empty
      <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center text-secondary py-5">Tidak ada absen berfoto pada filter ini.</div></div></div>
    @endforelse
  </div>
  <div class="mt-3">{{ $records->links() }}</div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
  @endpush
@endsection
