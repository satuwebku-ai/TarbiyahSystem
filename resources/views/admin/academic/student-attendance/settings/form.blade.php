@extends('admin._layout')
@section('title', $school ? 'Pengaturan Khusus Sekolah' : 'Ubah Pengaturan Umum')
@section('parent', 'Pengaturan Absensi Siswa')
@section('parent_url', route('admin.academic.student-attendance.settings.index'))
@section('content')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <form method="POST" action="{{ $school ? route('admin.academic.student-attendance.settings.school.update', $school) : route('admin.academic.student-attendance.settings.general.update') }}" class="card border-0 shadow-sm">
    @csrf @method('PUT')
    <div class="card-body p-4">
      @if($school)
        <div class="alert alert-light border small">
          <strong>{{ $school->unit?->name }}</strong> —
          {{ $hasCustom ? 'memakai pengaturan khusus.' : 'saat ini mengikuti pengaturan umum; nilai di bawah adalah salinannya dan baru menjadi pengaturan khusus setelah disimpan.' }}
        </div>
      @endif
      @php($days = array_map('intval', old('school_days', $setting->school_days ?? [])))
      <div class="mb-4">
        <h5 class="mb-1">Jam &amp; hari sekolah</h5>
        <p class="text-secondary small">Dipakai untuk menentukan hadir atau terlambat dari ketukan fingerprint.</p>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label d-block">Hari sekolah</label>
            @foreach(\App\Models\AttendancePolicy::DAY_NAMES as $n => $label)
              <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="school_days[]" id="d{{ $n }}" value="{{ $n }}" @checked(in_array($n, $days, true))><label class="form-check-label" for="d{{ $n }}">{{ $label }}</label></div>
            @endforeach
          </div>
          <div class="col-md-3"><label class="form-label" for="start_time">Jam masuk</label><input id="start_time" type="time" name="start_time" value="{{ old('start_time', $setting->start_time) }}" class="form-control @error('start_time') is-invalid @enderror" required></div>
          <div class="col-md-3"><label class="form-label" for="late_tolerance_minutes">Toleransi terlambat (menit)</label><input id="late_tolerance_minutes" type="number" min="0" max="180" name="late_tolerance_minutes" value="{{ old('late_tolerance_minutes', $setting->late_tolerance_minutes) }}" class="form-control @error('late_tolerance_minutes') is-invalid @enderror" required></div>
          <div class="col-md-3"><label class="form-label" for="checkin_open_time">Absen dibuka</label><input id="checkin_open_time" type="time" name="checkin_open_time" value="{{ old('checkin_open_time', $setting->checkin_open_time) }}" class="form-control @error('checkin_open_time') is-invalid @enderror" required></div>
          <div class="col-md-3"><label class="form-label" for="checkin_close_time">Absen ditutup</label><input id="checkin_close_time" type="time" name="checkin_close_time" value="{{ old('checkin_close_time', $setting->checkin_close_time) }}" class="form-control @error('checkin_close_time') is-invalid @enderror" required></div>
          <div class="col-12"><div class="form-check form-switch">
            <input type="hidden" name="skip_holidays" value="0"><input class="form-check-input" type="checkbox" role="switch" id="skip_holidays" name="skip_holidays" value="1" @checked(old('skip_holidays', $setting->skip_holidays))>
            <label class="form-check-label" for="skip_holidays">Lewati hari libur (sesuai menu Hari Libur di Absensi Pegawai)</label></div></div>
        </div>
      </div>

      <hr class="my-4">
      <div class="mb-4">
        <h5 class="mb-1">Fingerprint</h5>
        <div class="row g-3">
          <div class="col-12"><div class="form-check form-switch">
            <input type="hidden" name="fingerprint_enabled" value="0"><input class="form-check-input" type="checkbox" role="switch" id="fingerprint_enabled" name="fingerprint_enabled" value="1" @checked(old('fingerprint_enabled', $setting->fingerprint_enabled))>
            <label class="form-check-label" for="fingerprint_enabled">Terima absensi dari mesin fingerprint</label>
            <div class="form-text">Bila dimatikan, ketukan tetap tersimpan sebagai log tetapi tidak menjadi absensi.</div></div></div>
          <div class="col-md-4"><label class="form-label" for="session_name">Nama sesi absensi harian</label>
            <input id="session_name" name="session_name" value="{{ old('session_name', $setting->session_name) }}" class="form-control @error('session_name') is-invalid @enderror" maxlength="60" required>
            <div class="form-text">Absensi dari mesin dicatat pada sesi ini. Samakan dengan nama sesi yang dipakai guru saat input manual.</div></div>
        </div>
      </div>

      <hr class="my-4">
      <div>
        <h5 class="mb-1">Alpa otomatis</h5>
        <p class="text-secondary small">Siswa yang belum mengetuk jari sampai waktu ini ditandai Alpa. Bila ia mengetuk jari sesudahnya (selama absen belum ditutup), statusnya otomatis diperbarui dan dicatat sebagai koreksi. Catatan manual tidak pernah ditimpa.</p>
        <div class="row g-3">
          <div class="col-12"><div class="form-check form-switch">
            <input type="hidden" name="auto_absent_enabled" value="0"><input class="form-check-input" type="checkbox" role="switch" id="auto_absent_enabled" name="auto_absent_enabled" value="1" @checked(old('auto_absent_enabled', $setting->auto_absent_enabled))>
            <label class="form-check-label" for="auto_absent_enabled">Aktifkan alpa otomatis</label></div></div>
          <div class="col-md-3"><label class="form-label" for="auto_absent_time">Waktu penandaan</label><input id="auto_absent_time" type="time" name="auto_absent_time" value="{{ old('auto_absent_time', $setting->auto_absent_time) }}" class="form-control @error('auto_absent_time') is-invalid @enderror" required></div>
        </div>
        <div class="form-text mt-2">Membutuhkan penjadwal Laravel berjalan (<code>php artisan schedule:work</code> atau cron <code>schedule:run</code> tiap menit).</div>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
      <button class="btn btn-primary" type="submit"><x-icon name="check" size="16" class="me-1"/>Simpan pengaturan</button>
      <a href="{{ route('admin.academic.student-attendance.settings.index') }}" class="btn btn-light">Batal</a>
    </div>
  </form>
@endsection
