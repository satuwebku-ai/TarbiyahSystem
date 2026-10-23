<div class="card border-0 shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
      <div>
        <h5 class="mb-1">{{ $heading }} @if($badge)<span class="badge badge-soft-warning ms-1">{{ $badge }}</span>@endif</h5>
        <div class="small text-secondary">{{ $sub }}</div>
      </div>
      @if($editUrl)
        <a href="{{ $editUrl }}" class="btn btn-primary btn-sm"><x-icon name="edit" size="15" class="me-1"/>Ubah</a>
      @else
        <span class="small text-secondary">Hanya pengelola tingkat pusat yang dapat mengubah.</span>
      @endif
    </div>
    <div class="row g-3">
      <div class="col-sm-6 col-xl-3"><div class="text-secondary small">Jam masuk</div><div class="fs-5 fw-bold">{{ $s->start_time }}</div><div class="small text-secondary">Toleransi {{ $s->late_tolerance_minutes }} menit</div></div>
      <div class="col-sm-6 col-xl-3"><div class="text-secondary small">Jendela absen fingerprint</div><div class="fs-5 fw-bold">{{ $s->checkin_open_time }}–{{ $s->checkin_close_time }}</div><div class="small text-secondary">Ketukan di luar jam ini diabaikan</div></div>
      <div class="col-sm-6 col-xl-3"><div class="text-secondary small">Hari sekolah</div><div class="fw-semibold">{{ $s->dayLabels() }}</div><div class="small text-secondary">{{ $s->skip_holidays ? 'Hari libur dilewati' : 'Hari libur tidak dicek' }}</div></div>
      <div class="col-sm-6 col-xl-3"><div class="text-secondary small">Alpa otomatis</div><div class="fs-5 fw-bold">{{ $s->auto_absent_enabled ? $s->auto_absent_time : 'Nonaktif' }}</div><div class="small text-secondary">Sesi "{{ $s->session_name }}" · Fingerprint {{ $s->fingerprint_enabled ? 'aktif' : 'nonaktif' }}</div></div>
    </div>
  </div>
</div>
