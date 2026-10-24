@extends('pegawai._layout')
@section('title', 'Absen')
@section('content')
  @if($errors->any())
    <div class="alert alert-danger mb-0"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  @php $local = fn ($t) => \App\Helpers\AttendanceClock::toLocal($t, $tz)?->format('H:i'); @endphp
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
          <div class="small text-secondary">{{ \App\Models\AttendancePolicy::DAY_NAMES[$now->isoWeekday()] }}, {{ $now->format('d M Y') }} · {{ \App\Helpers\AttendanceClock::abbr($tz) }}</div>
          <h3 class="h5 fw-bold mt-1 mb-1">{{ $employee->name }} <span class="text-secondary fw-normal small">· {{ $employee->employee_number }}</span></h3>
          @if($unit)<div class="small text-secondary mb-3">{{ $unit }}</div>@else<div class="mb-3"></div>@endif

          @if($holiday)
            <div class="alert alert-secondary py-2">Hari ini libur: {{ $holiday->name }}.</div>
          @elseif(! $policy)
            <div class="alert alert-warning py-2">Kebijakan absensi untuk unit Anda belum diatur atau Anda belum punya penempatan berjalan. Hubungi pengelola.</div>
          @elseif(! $policy->isWorkDay($now->isoWeekday()))
            <div class="alert alert-secondary py-2">Hari ini bukan hari kerja.</div>
          @else
            <div class="small text-secondary mb-3">Jam kerja {{ substr($policy->work_start, 0, 5) }}–{{ substr($policy->work_end, 0, 5) }} · toleransi {{ $policy->late_tolerance_minutes }} menit</div>
          @endif

          <div class="row text-center g-2 mb-3">
            <div class="col-6"><div class="border rounded-3 p-3"><div class="small text-secondary">Masuk</div><div class="fs-4 fw-bold">{{ $local($today?->check_in_at) ?? '—' }}</div></div></div>
            <div class="col-6"><div class="border rounded-3 p-3"><div class="small text-secondary">Pulang</div><div class="fs-4 fw-bold">{{ $local($today?->check_out_at) ?? '—' }}</div></div></div>
          </div>
          @if($today)<div class="mb-3"><span class="badge {{ $today->status->badge() }}">{{ $today->status->label() }}</span>@if($today->late_minutes) <span class="small text-secondary">terlambat {{ $today->late_minutes }} menit</span>@endif</div>@endif

          @if($needsPhoto || $needsLocation)
            <div class="small text-secondary mb-2">
              Absen memerlukan: @if($needsPhoto)<span class="badge text-bg-light border">Foto selfie</span>@endif @if($needsLocation)<span class="badge text-bg-light border">Lokasi di sekolah (radius {{ $policy->geo_radius_m }} m)</span>@endif
            </div>
          @endif
          <div class="d-flex gap-2">
            @php($actions = ['check-in' => ['Check-in', 'btn-primary', (bool) $canIn], 'check-out' => ['Check-out', 'btn-outline-primary', (bool) $canOut]])
            @foreach($actions as $route => [$label, $btn, $enabled])
              <form method="POST" action="{{ route('pegawai.'.$route) }}" enctype="multipart/form-data" @if($needsPhoto || $needsLocation) data-proof data-title="{{ $label }}" data-photo="{{ $needsPhoto ? 1 : 0 }}" data-location="{{ $needsLocation ? 1 : 0 }}" @endif>
                @csrf
                @if($needsPhoto || $needsLocation)
                  <input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy">
                  <input type="file" name="photo" accept="image/*" class="d-none">
                @endif
                <button class="btn {{ $btn }}" type="submit" @disabled(! $enabled)>{{ $label }}</button>
              </form>
            @endforeach
          </div>
          <div class="form-text mt-2">Waktu dicatat dari jam server ({{ \App\Helpers\AttendanceClock::abbr($tz) }}), bukan dari perangkat Anda.</div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Ajukan koreksi</div>
        <div class="card-body">
          <form method="POST" action="{{ route('pegawai.corrections.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4"><label class="form-label small mb-1" for="c_date">Tanggal</label><input id="c_date" type="date" name="attendance_date" value="{{ old('attendance_date') }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label small mb-1" for="c_in">Jam masuk benar</label><input id="c_in" type="time" name="requested_check_in" value="{{ old('requested_check_in') }}" class="form-control"></div>
            <div class="col-md-4"><label class="form-label small mb-1" for="c_out">Jam pulang benar</label><input id="c_out" type="time" name="requested_check_out" value="{{ old('requested_check_out') }}" class="form-control"></div>
            <div class="col-12"><label class="form-label small mb-1" for="c_reason">Alasan</label><input id="c_reason" name="reason" value="{{ old('reason') }}" class="form-control" maxlength="500" required></div>
            <div class="col-12 d-flex justify-content-between align-items-center gap-2">
              <span class="form-text">Maksimal {{ config('attendance.correction_window_days') }} hari ke belakang. Disetujui oleh atasan/pengelola unit.</span>
              <button class="btn btn-primary" type="submit">Kirim pengajuan</button>
            </div>
          </form>
          @if($corrections->isNotEmpty())
            <hr>
            @foreach($corrections as $c)
              <div class="d-flex justify-content-between gap-2 small py-1">
                <span>{{ $c->attendance_date->format('d M Y') }} · {{ $c->reason }}</span>
                <span class="badge {{ $c->status->badge() }}">{{ $c->status->label() }}</span>
              </div>
              @if($c->review_note)<div class="small text-secondary ps-2">Catatan: {{ $c->review_note }}</div>@endif
            @endforeach
          @endif
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <span class="fw-semibold">7 catatan terakhir</span>
          <a href="{{ route('pegawai.history') }}" class="small text-decoration-none">Lihat riwayat bulanan</a>
        </div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Terlambat</th></tr></thead>
            <tbody>
              @forelse($recent as $r)
                <tr><td>{{ $r->attendance_date->format('d M Y') }}</td><td><span class="badge {{ $r->status->badge() }}">{{ $r->status->label() }}</span></td><td>{{ $local($r->check_in_at) ?? '—' }}</td><td>{{ $local($r->check_out_at) ?? '—' }}</td><td>{{ $r->late_minutes ? $r->late_minutes.' menit' : '—' }}</td></tr>
              @empty
                <tr><td colspan="5" class="empty-state py-4">Belum ada catatan absensi.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  @if($needsPhoto || $needsLocation)
    <div class="modal fade" id="proofModal" tabindex="-1" aria-labelledby="proofTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title" id="proofTitle">Verifikasi absen</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
          <div class="modal-body">
            <div id="proofCam" class="mb-3 d-none">
              <video id="proofVideo" class="w-100 rounded-3 bg-dark" autoplay playsinline muted style="max-height:320px;object-fit:cover;transform:scaleX(-1)"></video>
              <canvas id="proofCanvas" class="d-none"></canvas>
              <div class="form-text">Pastikan wajah Anda terlihat jelas.</div>
            </div>
            <div id="proofGeo" class="small"></div>
            <div id="proofErr" class="alert alert-danger py-2 small mt-2 mb-0 d-none"></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="button" id="proofGo" class="btn btn-primary" disabled>Kirim</button></div>
        </div>
      </div>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('proofModal'), modal = new bootstrap.Modal(el);
        var video = document.getElementById('proofVideo'), canvas = document.getElementById('proofCanvas');
        var cam = document.getElementById('proofCam'), geoBox = document.getElementById('proofGeo');
        var errBox = document.getElementById('proofErr'), go = document.getElementById('proofGo'), title = document.getElementById('proofTitle');
        var stream = null, active = null, pos = null;

        function stop() { if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } video.srcObject = null; }
        function fail(m) { errBox.textContent = m; errBox.classList.remove('d-none'); }
        function refresh() { go.disabled = !((!active.loc || pos) && (!active.photo || stream)); }

        document.querySelectorAll('form[data-proof]').forEach(function (f) {
          f.addEventListener('submit', function (e) {
            if (f.dataset.ready === '1') return;
            e.preventDefault();
            active = { form: f, photo: f.dataset.photo === '1', loc: f.dataset.location === '1' };
            title.textContent = f.dataset.title;
            modal.show();
          });
        });

        el.addEventListener('shown.bs.modal', function () {
          pos = null; errBox.classList.add('d-none'); geoBox.textContent = ''; go.disabled = true;
          cam.classList.toggle('d-none', !active.photo);
          if (active.photo) {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
              fail('Kamera tidak tersedia. Buka portal lewat HTTPS atau browser yang mendukung kamera.');
            } else {
              navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } }, audio: false })
                .then(function (s) { stream = s; video.srcObject = s; refresh(); })
                .catch(function () { fail('Izin kamera ditolak atau kamera tidak ditemukan. Izinkan kamera pada browser lalu coba lagi.'); });
            }
          }
          if (active.loc) {
            geoBox.textContent = 'Mengambil lokasi...';
            if (!navigator.geolocation) { geoBox.textContent = ''; fail('Browser tidak mendukung lokasi.'); return; }
            navigator.geolocation.getCurrentPosition(function (p) {
              pos = p; geoBox.textContent = 'Lokasi didapat (akurasi ±' + Math.round(p.coords.accuracy) + ' m).'; refresh();
            }, function () {
              geoBox.textContent = ''; fail('Lokasi tidak dapat diambil. Aktifkan GPS dan izinkan akses lokasi pada browser.');
            }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
          }
        });
        el.addEventListener('hidden.bs.modal', stop);

        function submit() {
          var f = active.form;
          if (pos) {
            f.querySelector('[name=latitude]').value = pos.coords.latitude;
            f.querySelector('[name=longitude]').value = pos.coords.longitude;
            f.querySelector('[name=accuracy]').value = pos.coords.accuracy;
          }
          f.dataset.ready = '1'; go.disabled = true; stop(); f.submit();
        }
        go.addEventListener('click', function () {
          if (!active.photo) { submit(); return; }
          var w = video.videoWidth, h = video.videoHeight;
          if (!w || !h) { fail('Kamera belum siap, tunggu sebentar lalu coba lagi.'); return; }
          var scale = Math.min(1, 640 / w);
          canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
          canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
          canvas.toBlob(function (blob) {
            if (!blob) { fail('Gagal mengambil foto. Coba lagi.'); return; }
            var dt = new DataTransfer();
            dt.items.add(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }));
            active.form.querySelector('[name=photo]').files = dt.files;
            submit();
          }, 'image/jpeg', 0.7);
        });
      });
    </script>
  @endif
@endsection
