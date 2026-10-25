@extends('admin._layout')
@section('title', 'Pengaturan Aplikasi')
@section('parent', 'Pengaturan Admin')
@section('parent_url', route('admin.settings.index'))
@section('content')
  <ul class="nav nav-pills mb-3 gap-1 flex-wrap">
    @foreach ($groups as $groupKey => $group)
      <li class="nav-item">
        <a class="nav-link {{ $activeTab === $groupKey ? 'active' : '' }}"
           href="{{ route('admin.settings.index', ['tab' => $groupKey]) }}">{{ $group['label'] }}</a>
      </li>
    @endforeach
  </ul>

  @php($group = $groups[$activeTab])
  <form method="POST" action="{{ route('admin.settings.update', $activeTab) }}" class="card border-0 shadow-sm" autocomplete="off">
    @csrf
    @method('PUT')
    <div class="card-body">
      <div class="mb-4">
        <h5 class="mb-1">{{ $group['label'] }}</h5>
        <p class="text-secondary small mb-0">{{ $group['description'] }} Nilai di sini menimpa isi <code>.env</code>.</p>
      </div>

      <div class="row g-3">
        @foreach ($group['fields'] as $key => $field)
          @php($value = old($key, $settings->current($key)))
          @if ($field['type'] === 'bool')
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="{{ $key }}" name="{{ $key }}" value="1" @checked($value)>
                <label class="form-check-label" for="{{ $key }}">{{ $field['label'] }}</label>
              </div>
              @isset($field['help']) <div class="form-text">{{ $field['help'] }}</div> @endisset
              @error($key) <div class="text-danger small">{{ $message }}</div> @enderror
            </div>
          @else
            <div class="col-md-6">
              <label for="{{ $key }}" class="form-label">{{ $field['label'] }}</label>
              @if ($field['type'] === 'select')
                <select id="{{ $key }}" name="{{ $key }}" class="form-select @error($key) is-invalid @enderror">
                  @foreach ($field['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                  @endforeach
                </select>
              @elseif ($field['type'] === 'secret')
                <input id="{{ $key }}" name="{{ $key }}" type="password" autocomplete="new-password"
                       placeholder="{{ $settings->hasSecret($key) ? '•••••••• (tersimpan, kosongkan bila tidak diubah)' : '' }}"
                       class="form-control @error($key) is-invalid @enderror">
              @else
                <input id="{{ $key }}" name="{{ $key }}" type="{{ $field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : 'text') }}"
                       value="{{ $value }}" class="form-control @error($key) is-invalid @enderror">
              @endif
              @isset($field['help']) <div class="form-text">{{ $field['help'] }}</div> @endisset
              @error($key) <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
          @endif
        @endforeach
      </div>

      @if ($activeTab === 'captcha')
        @php($captchaSvc = app(\App\Services\Captcha::class))
        @if ($captchaSvc->isMisconfigured())
          <div class="alert alert-warning small mt-4 mb-0">Jenis CAPTCHA eksternal dipilih, tetapi site key atau secret key belum lengkap, jadi CAPTCHA <strong>belum diterapkan</strong>.</div>
        @elseif ($captchaSvc->provider() === 'off')
          <div class="alert alert-secondary small mt-4 mb-0">CAPTCHA nonaktif.</div>
        @else
          <div class="alert alert-success small mt-4 mb-0">CAPTCHA aktif ({{ $captchaSvc->provider() === 'math' ? 'pertanyaan hitung' : config('captcha.providers.'.$captchaSvc->provider().'.label') }}).</div>
        @endif
        <div class="alert alert-secondary small mt-3 mb-0">
          Mendaftarkan situs di Cloudflare Turnstile / Google reCAPTCHA / hCaptcha akan menghasilkan site key dan secret key; daftarkan domain panel, portal pegawai, dan portal pelajar.
          Header keamanan (CSP) otomatis mengizinkan host penyedia yang dipilih. Halaman 2FA tidak memakai CAPTCHA.
        </div>
      @endif

      @if ($activeTab === 'email')
        <div class="alert alert-secondary small mt-4 mb-0">
          Email dikirim lewat antrean: pastikan <code>php artisan queue:work</code> selalu berjalan. Bila <code>APP_KEY</code> diganti,
          password SMTP yang tersimpan tidak terbaca lagi dan harus diisi ulang.
        </div>
      @endif
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <span class="small text-secondary">Perubahan dicatat pada riwayat keamanan (tanpa nilai rahasia).</span>
      @can('settings.update')
        <button class="btn btn-primary" type="submit"><x-icon name="check" size="16" class="me-1"/>Simpan pengaturan</button>
      @endcan
    </div>
  </form>

  @if ($activeTab === 'email')
    @can('settings.update')
      <form method="POST" action="{{ route('admin.settings.test-mail') }}" class="card border-0 shadow-sm mt-3">
        @csrf
        <div class="card-body">
          <h6 class="mb-1">Kirim email uji</h6>
          <p class="text-secondary small">Memakai pengaturan yang <strong>sudah disimpan</strong>. Simpan dulu bila baru mengubah.</p>
          <div class="row g-2 align-items-start">
            <div class="col-md-6">
              <input name="test_email" type="email" required placeholder="alamat@tujuan.id" aria-label="Alamat tujuan email uji"
                     value="{{ old('test_email', auth('admin')->user()?->email) }}"
                     class="form-control @error('test_email') is-invalid @enderror">
              @error('test_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-auto">
              <button class="btn btn-outline-primary" type="submit"><x-icon name="mail" size="16" class="me-1"/>Kirim email uji</button>
            </div>
          </div>
        </div>
      </form>
    @endcan
  @endif
  @push('scripts')
    @include('partials.admin.clean-url')
  @endpush
@endsection
