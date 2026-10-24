@extends('admin._layout')
@php($isEdit = $policy->exists)
@section('title', $isEdit ? 'Ubah Kebijakan Absensi' : 'Tambah Kebijakan Absensi')
@section('parent', 'Kebijakan Absensi')
@section('parent_url', route('admin.attendance.policies.index'))
@section('content')
  @include('partials.admin.select2-assets')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="card border-0 shadow-sm"><div class="card-body p-4">
    <form method="POST" action="{{ $isEdit ? route('admin.attendance.policies.update', $policy) : route('admin.attendance.policies.store') }}">
      @csrf @if($isEdit) @method('PUT') @endif
      @php($days = old('work_days', $policy->work_days ?? []))
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Nama kebijakan</label><input id="name" name="name" value="{{ old('name', $policy->name) }}" class="form-control" required maxlength="120"></div>
        <div class="col-md-6"><label class="form-label" for="organizational_unit_id">Berlaku untuk</label>
          <select id="organizational_unit_id" name="organizational_unit_id" class="form-select js-unit-select" data-placeholder="Umum (seluruh organisasi)">
            @if($canGlobal)<option value="">Umum (seluruh organisasi)</option>@endif
            @foreach($unitOptions as $o)
              <option value="{{ $o['id'] }}" data-name="{{ $o['name'] }}" data-code="{{ $o['code'] }}" data-depth="{{ $o['depth'] }}" data-path="{{ $o['path'] }}" @selected(old('organizational_unit_id', $policy->organizational_unit_id) == $o['id'])>{{ $o['name'] }} · {{ $o['code'] }}</option>
            @endforeach
          </select></div>
        <div class="col-12"><label class="form-label d-block">Hari kerja</label>
          @foreach(\App\Models\AttendancePolicy::DAY_NAMES as $n => $label)
            <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="work_days[]" id="d{{ $n }}" value="{{ $n }}" @checked(in_array($n, array_map('intval', $days), true))><label class="form-check-label" for="d{{ $n }}">{{ $label }}</label></div>
          @endforeach</div>
        <div class="col-md-4"><label class="form-label" for="work_start">Jam masuk</label><input id="work_start" type="time" name="work_start" value="{{ old('work_start', substr((string) $policy->work_start, 0, 5)) }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label" for="work_end">Jam pulang</label><input id="work_end" type="time" name="work_end" value="{{ old('work_end', substr((string) $policy->work_end, 0, 5)) }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label" for="late_tolerance_minutes">Toleransi terlambat (menit)</label><input id="late_tolerance_minutes" type="number" min="0" max="240" name="late_tolerance_minutes" value="{{ old('late_tolerance_minutes', $policy->late_tolerance_minutes) }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label" for="timezone">Zona waktu</label>
          <select id="timezone" name="timezone" class="form-select" required>
            @foreach(config('attendance.timezones') as $zone => [$abbr, $label])
              <option value="{{ $zone }}" @selected(old('timezone', $policy->timezone ?: config('attendance.timezone')) === $zone)>{{ $label }}</option>
            @endforeach
          </select>
          <div class="form-text">Jam masuk/pulang dan batas hari absen mengikuti zona ini.</div></div>
        <div class="col-12"><div class="form-check"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $policy->is_active))><label class="form-check-label" for="is_active">Aktif</label></div></div>
        <div class="col-12"><hr class="my-1"><div class="fw-semibold">Bukti absen (opsional)</div></div>
        <div class="col-12">
          <div class="form-check"><input type="hidden" name="require_photo" value="0"><input class="form-check-input" type="checkbox" id="require_photo" name="require_photo" value="1" @checked(old('require_photo', $policy->require_photo))><label class="form-check-label" for="require_photo">Wajib foto selfie dari kamera saat check-in dan check-out</label></div>
          <div class="form-check mt-2"><input type="hidden" name="require_location" value="0"><input class="form-check-input" type="checkbox" id="require_location" name="require_location" value="1" @checked(old('require_location', $policy->require_location))><label class="form-check-label" for="require_location">Wajib absen di lokasi sekolah (GPS)</label></div>
        </div>
        <div id="geo-fields" class="row g-3 mx-0 px-0 {{ old('require_location', $policy->require_location) ? '' : 'd-none' }}">
          <div class="col-md-4"><label class="form-label" for="geo_latitude">Latitude sekolah</label><input id="geo_latitude" name="geo_latitude" value="{{ old('geo_latitude', $policy->geo_latitude) }}" class="form-control" inputmode="decimal" placeholder="-0.8917"></div>
          <div class="col-md-4"><label class="form-label" for="geo_longitude">Longitude sekolah</label><input id="geo_longitude" name="geo_longitude" value="{{ old('geo_longitude', $policy->geo_longitude) }}" class="form-control" inputmode="decimal" placeholder="119.8707"></div>
          <div class="col-md-4"><label class="form-label" for="geo_radius_m">Radius diizinkan (meter)</label><input id="geo_radius_m" type="number" min="20" max="5000" name="geo_radius_m" value="{{ old('geo_radius_m', $policy->geo_radius_m ?? 100) }}" class="form-control"></div>
          <div class="col-12 d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="geo-here">Pakai lokasi saya sekarang</button>
            <a id="geo-map" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-light d-none">Lihat di peta</a>
            <span id="geo-msg" class="small text-secondary">Buka halaman ini dari perangkat yang berada di sekolah, atau salin koordinat dari Google Maps (klik kanan pada titik sekolah).</span>
          </div>
        </div>
      </div>
      <div class="alert alert-light border small mt-3 mb-0">Terlambat dihitung bila check-in melewati jam masuk (menurut zona waktu kebijakan) ditambah toleransi; menit terlambat dihitung dari jam masuk resmi. Perubahan tidak mengubah catatan yang sudah ada. Syarat foto dan lokasi berlaku untuk check-in dan check-out; foto hanya bisa dilihat pengelola yang berhak melihat rekap absensi.</div>
      <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('admin.attendance.policies.index') }}" class="btn btn-light">Batal</a><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
  </div></div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var box = document.getElementById('require_location'), fields = document.getElementById('geo-fields');
    var lat = document.getElementById('geo_latitude'), lng = document.getElementById('geo_longitude');
    var map = document.getElementById('geo-map'), msg = document.getElementById('geo-msg');
    var sync = function () { fields.classList.toggle('d-none', !box.checked); };
    var link = function () {
      var ok = lat.value.trim() && lng.value.trim();
      map.classList.toggle('d-none', !ok);
      if (ok) map.href = 'https://www.google.com/maps?q=' + encodeURIComponent(lat.value.trim() + ',' + lng.value.trim());
    };
    box.addEventListener('change', sync); lat.addEventListener('input', link); lng.addEventListener('input', link); sync(); link();
    document.getElementById('geo-here').addEventListener('click', function () {
      if (!navigator.geolocation) { msg.textContent = 'Browser tidak mendukung lokasi.'; return; }
      msg.textContent = 'Mengambil lokasi...';
      navigator.geolocation.getCurrentPosition(function (p) {
        lat.value = p.coords.latitude.toFixed(7); lng.value = p.coords.longitude.toFixed(7); link();
        msg.textContent = 'Lokasi terisi (akurasi ±' + Math.round(p.coords.accuracy) + ' m). Periksa lewat tombol Lihat di peta.';
      }, function () { msg.textContent = 'Lokasi tidak dapat diambil. Izinkan akses lokasi atau isi koordinat manual.'; }, { enableHighAccuracy: true, timeout: 15000 });
    });
  });
</script>
@endsection
