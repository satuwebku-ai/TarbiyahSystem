@extends('admin._layout')
@section('title', 'Kebijakan Keamanan')
@section('parent', 'Pengaturan Admin')
@section('parent_url', route('admin.security.index'))
@section('actions')
  @can('security.activity')
    <a href="{{ route('admin.security.activity') }}" class="btn btn-outline-primary">
      <x-icon name="clock" size="16" class="me-1"/>Riwayat Aktivitas
    </a>
  @endcan
@endsection
@section('content')
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Percobaan login</div>
        <div class="fs-4 fw-bold">{{ $settings->max_login_attempts }} kali</div>
        <div class="small text-secondary">Blokir {{ $settings->lockout_minutes }} menit setelah batas tercapai</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Sesi tidak aktif</div>
        <div class="fs-4 fw-bold">{{ $settings->session_idle_minutes }} menit</div>
        <div class="small text-secondary">Admin akan diminta masuk kembali</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Panjang kata sandi minimum</div>
        <div class="fs-4 fw-bold">{{ $settings->password_min_length }} karakter</div>
        <div class="small text-secondary">Berlaku saat membuat atau mengganti password admin</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Audit keamanan</div>
        <div class="fs-4 fw-bold">Aktif</div>
        <div class="small text-secondary">Login, perubahan akun, role, dan kebijakan</div>
      </div></div>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.security.update') }}" class="card border-0 shadow-sm">
    @csrf
    @method('PUT')
    <div class="card-body">
      <div class="mb-4">
        <h5 class="mb-1">Perlindungan login dan sesi</h5>
        <p class="text-secondary small mb-0">Aturan ini hanya berlaku untuk akun pengelola.</p>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label for="max_login_attempts" class="form-label">Maksimum percobaan login</label>
          <input id="max_login_attempts" name="max_login_attempts" type="number" min="3" max="20"
                 value="{{ old('max_login_attempts', $settings->max_login_attempts) }}"
                 class="form-control @error('max_login_attempts') is-invalid @enderror" required>
          <div class="form-text">Pilih antara 3–20 percobaan.</div>
          @error('max_login_attempts') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label for="lockout_minutes" class="form-label">Lama pemblokiran (menit)</label>
          <input id="lockout_minutes" name="lockout_minutes" type="number" min="1" max="60"
                 value="{{ old('lockout_minutes', $settings->lockout_minutes) }}"
                 class="form-control @error('lockout_minutes') is-invalid @enderror" required>
          <div class="form-text">Pilih antara 1–60 menit.</div>
          @error('lockout_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label for="session_idle_minutes" class="form-label">Batas sesi tidak aktif (menit)</label>
          <input id="session_idle_minutes" name="session_idle_minutes" type="number" min="5" max="240"
                 value="{{ old('session_idle_minutes', $settings->session_idle_minutes) }}"
                 class="form-control @error('session_idle_minutes') is-invalid @enderror" required>
          <div class="form-text">Pilih antara 5 menit–4 jam.</div>
          @error('session_idle_minutes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      </div>

      <hr class="my-4">
      <div class="mb-3">
        <h5 class="mb-1">Kebijakan kata sandi admin</h5>
        <p class="text-secondary small mb-0">Diterapkan pada pembuatan admin, ubah password, dan reset password.</p>
      </div>
      <div class="row g-3 align-items-start">
        <div class="col-md-4">
          <label for="password_min_length" class="form-label">Panjang minimum (karakter)</label>
          <input id="password_min_length" name="password_min_length" type="number" min="8" max="64"
                 value="{{ old('password_min_length', $settings->password_min_length) }}"
                 class="form-control @error('password_min_length') is-invalid @enderror" required>
          <div class="form-text">Pilih antara 8–64 karakter; password harus memiliki huruf.</div>
          @error('password_min_length') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-8">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="password_require_mixed_case"
                   name="password_require_mixed_case" value="1" @checked(old('password_require_mixed_case', $settings->password_require_mixed_case))>
            <label class="form-check-label" for="password_require_mixed_case">Wajib huruf besar dan huruf kecil</label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="password_require_numbers"
                   name="password_require_numbers" value="1" @checked(old('password_require_numbers', $settings->password_require_numbers))>
            <label class="form-check-label" for="password_require_numbers">Wajib angka</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="password_require_symbols"
                   name="password_require_symbols" value="1" @checked(old('password_require_symbols', $settings->password_require_symbols))>
            <label class="form-check-label" for="password_require_symbols">Wajib simbol (contoh: ! @ #)</label>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <span class="small text-secondary">Perubahan kebijakan akan dicatat pada riwayat keamanan.</span>
      @can('security.update')
        <button class="btn btn-primary" type="submit"><x-icon name="check" size="16" class="me-1"/>Simpan kebijakan</button>
      @endcan
    </div>
  </form>
@endsection
