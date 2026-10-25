{{-- Kartu 2FA di halaman profil staf. Butuh: $user, $prefix ('admin' | 'pegawai'). --}}
@php
  $featureOn = \App\Services\TwoFactorPolicy::featureEnabled();
  $required = \App\Services\TwoFactorPolicy::isRequiredFor($user);
  $enabled = $user->hasTwoFactorEnabled();
  $pending = $user->hasPendingTwoFactorSetup();
  $freshCodes = session('two_factor_recovery_codes');
  $r = fn (string $name) => route($prefix.'.profile.two-factor.'.$name);
@endphp
@if ($featureOn || $enabled || $pending)
<div class="card border-0 shadow-sm mt-3" id="two-factor">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
      <div>
        <h6 class="mb-1">Autentikasi dua faktor (2FA)</h6>
        <p class="text-secondary small mb-0">Selain kata sandi, login meminta kode 6 digit dari aplikasi autentikator di ponsel Anda.</p>
      </div>
      @if ($enabled && $featureOn)
        <span class="badge text-bg-success">Aktif</span>
      @elseif ($enabled)
        <span class="badge text-bg-secondary">Aktif (fitur sedang dimatikan pengelola)</span>
      @else
        <span class="badge text-bg-secondary">Belum aktif</span>
      @endif
    </div>

    @if ($required && ! $enabled)
      <div class="alert alert-warning small">Kebijakan keamanan <strong>mewajibkan</strong> 2FA untuk akun Anda. Selesaikan pendaftaran di bawah ini untuk melanjutkan memakai aplikasi.</div>
    @endif
    @if (! $featureOn && ! $enabled)
      <div class="alert alert-secondary small mb-0">Fitur 2FA sedang dimatikan oleh pengelola.</div>
    @endif

    @if (is_array($freshCodes))
      <div class="alert alert-warning">
        <div class="fw-semibold mb-1">Simpan kode pemulihan ini sekarang</div>
        <div class="small mb-2">Kode ini hanya ditampilkan sekali. Tiap kode bisa dipakai satu kali bila ponsel Anda hilang. Simpan di tempat aman (mis. pengelola kata sandi atau dicetak).</div>
        <div class="row g-1 font-monospace" id="recovery-codes">
          @foreach ($freshCodes as $c) <div class="col-6 col-md-3">{{ $c }}</div> @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2"
                onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('recovery-codes').innerText.trim().split(/\s+/).join('\n')); this.textContent='Tersalin'">Salin semua</button>
      </div>
    @endif

    @if ($pending && $featureOn)
      @php
        $secret = (string) $user->two_factor_secret;
        $uri = app(\App\Services\TwoFactorAuthenticator::class)->provisioningUri(config('app.name'), $user->username ?? $user->email, $secret);
      @endphp
      <div class="row g-4 align-items-start">
        <div class="col-md-5 text-center">
          <div id="two-factor-qr" class="mx-auto border rounded p-2 bg-white" style="max-width:220px"></div>
          <div class="small text-secondary mt-2">Pindai dengan aplikasi autentikator</div>
        </div>
        <div class="col-md-7">
          <ol class="small ps-3">
            <li class="mb-2">Pindai kode QR di samping, atau masukkan kunci ini secara manual: <div class="font-monospace user-select-all fs-6 mt-1">{{ trim(chunk_split($secret, 4, ' ')) }}</div></li>
            <li>Masukkan kode 6 digit yang tampil di aplikasi untuk menyelesaikan.</li>
          </ol>
          <form method="POST" action="{{ $r('confirm') }}" class="d-flex gap-2 flex-wrap">
            @csrf
            <input name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required
                   class="form-control @error('two_factor_code') is-invalid @enderror" style="max-width:10rem">
            <button class="btn btn-primary">Aktifkan</button>
          </form>
          @error('two_factor_code') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
      </div>
      <script src="{{ asset('assets/vendor/qrcode.js') }}"></script>
      <script>
        (function () {
          var qr = qrcode(0, 'M');
          qr.addData(@json($uri));
          qr.make();
          document.getElementById('two-factor-qr').innerHTML = qr.createSvgTag({cellSize: 4, margin: 2, scalable: true});
        })();
      </script>
      <form method="POST" action="{{ $r('disable') }}" class="mt-3 d-flex gap-2 flex-wrap align-items-center" onsubmit="return confirm('Batalkan pendaftaran 2FA?')">
        @csrf @method('DELETE')
        <input type="password" name="two_factor_password" required placeholder="Kata sandi (untuk membatalkan)" autocomplete="current-password" class="form-control form-control-sm" style="max-width:16rem">
        <button class="btn btn-sm btn-outline-secondary">Batalkan</button>
      </form>
    @elseif ($enabled)
      <div class="small text-secondary mb-3">Sisa kode pemulihan: <strong>{{ $user->recoveryCodesRemaining() }}</strong>
        @if ($user->recoveryCodesRemaining() <= 2) <span class="text-danger">— hampir habis, buat kode baru.</span> @endif
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <form method="POST" action="{{ $r('recovery-codes') }}" class="border rounded p-3 h-100">
            @csrf
            <div class="fw-semibold small mb-2">Buat ulang kode pemulihan</div>
            <input type="password" name="two_factor_password" required placeholder="Kata sandi saat ini" autocomplete="current-password" class="form-control form-control-sm mb-2">
            <button class="btn btn-sm btn-outline-primary">Buat kode baru</button>
          </form>
        </div>
        <div class="col-md-6">
          <form method="POST" action="{{ $r('disable') }}" class="border rounded p-3 h-100" onsubmit="return confirm('Matikan 2FA untuk akun ini?')">
            @csrf @method('DELETE')
            <div class="fw-semibold small mb-2">Matikan 2FA</div>
            <input type="password" name="two_factor_password" required placeholder="Kata sandi saat ini" autocomplete="current-password" class="form-control form-control-sm mb-2">
            <button class="btn btn-sm btn-outline-danger" @disabled($required)>Matikan</button>
            @if ($required) <div class="form-text">Tidak bisa dimatikan: 2FA diwajibkan oleh kebijakan keamanan.</div> @endif
          </form>
        </div>
      </div>
      @error('two_factor_password') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
    @elseif ($featureOn)
      <form method="POST" action="{{ $r('enable') }}">
        @csrf
        <p class="small text-secondary">Anda butuh aplikasi autentikator (Google Authenticator, Microsoft Authenticator, Authy, atau sejenisnya) di ponsel.</p>
        <button class="btn btn-primary">Aktifkan 2FA</button>
      </form>
    @endif
  </div>
</div>
@endif
