@extends('admin._layout')
@php($isEdit = $connection !== null)
@section('title', 'Koneksi Node Fingerprint')
@section('parent', 'Pengaturan Absensi Siswa')
@section('parent_url', route('admin.academic.student-attendance.settings.index'))
@section('content')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <form method="POST" action="{{ route('admin.academic.student-attendance.settings.node.update', $school) }}" class="card border-0 shadow-sm">
    @csrf @method('PUT')
    <div class="card-body p-4">
      <div class="alert alert-light border small">
        <strong>{{ $school->unit?->name }}</strong> —
        {{ $isEdit ? 'koneksi Node sudah dibuat.' : 'belum punya koneksi Node. Token dibuat otomatis setelah disimpan dan hanya ditampilkan sekali.' }}
      </div>

      <h5 class="mb-1">Kredensial aplikasi Node</h5>
      <p class="text-secondary small">Aplikasi Node di sekolah memakai username + password (Basic auth) dan token (header <code>X-Node-Token</code>). Ketiganya harus cocok.</p>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="username">Username <span class="text-danger">*</span></label>
          <input id="username" name="username" value="{{ old('username', $connection?->username) }}" class="form-control font-monospace @error('username') is-invalid @enderror" minlength="4" maxlength="40" autocomplete="off" required>
          <div class="form-text">Huruf kecil, angka, titik, garis bawah, strip.</div>
        </div>
        <div class="col-md-5">
          <label class="form-label" for="password">Password @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
          <div class="input-group">
            <input id="password" type="password" name="password" class="form-control font-monospace @error('password') is-invalid @enderror" minlength="10" maxlength="100" autocomplete="new-password" @required(! $isEdit)>
            <button type="button" class="btn btn-outline-secondary" id="pw-toggle">Lihat</button>
            <button type="button" class="btn btn-outline-secondary" id="pw-generate">Buat acak</button>
          </div>
          <div class="form-text">{{ $isEdit ? 'Kosongkan bila tidak ingin mengganti.' : 'Minimal 10 karakter.' }} Password disimpan terenkripsi (hash) dan tidak bisa dilihat lagi, catat sebelum menyimpan.</div>
        </div>
        <div class="col-md-3">
          <label class="form-label">Token</label>
          <input class="form-control font-monospace" value="{{ $isEdit ? $connection->token_prefix.'••••••••' : 'Dibuat saat disimpan' }}" disabled>
          @if($isEdit)<div class="form-text">Dibuat {{ $connection->token_rotated_at?->translatedFormat('d M Y H:i') ?? '—' }}.</div>@endif
        </div>
        <div class="col-12">
          <label class="form-label" for="allowed_ips">Batasi IP pengirim <span class="text-secondary">(opsional)</span></label>
          <input id="allowed_ips" name="allowed_ips" value="{{ old('allowed_ips', $connection?->allowed_ips) }}" class="form-control @error('allowed_ips') is-invalid @enderror" maxlength="255" placeholder="203.0.113.10, 10.0.0.0/24">
          <div class="form-text">IP/CIDR dipisah koma. Kosong = semua IP diterima (tetap wajib username, password, dan token).</div>
        </div>
        <div class="col-12"><div class="form-check form-switch">
          <input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $connection?->is_active ?? true))>
          <label class="form-check-label" for="is_active">Koneksi aktif</label>
          <div class="form-text">Bila dimatikan, aplikasi Node ditolak (403) dan menyimpan data di sekolah untuk dikirim ulang nanti.</div></div></div>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
      <button class="btn btn-primary" type="submit"><x-icon name="check" size="16" class="me-1"/>{{ $isEdit ? 'Simpan perubahan' : 'Buat koneksi' }}</button>
      <a href="{{ route('admin.academic.student-attendance.settings.index') }}" class="btn btn-light">Batal</a>
    </div>
  </form>

  @if($isEdit)
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-body p-4 d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <div><div class="fw-semibold">Token & penghapusan</div><div class="small text-secondary">Token baru membuat token lama langsung tidak berlaku. Menghapus koneksi memutus aplikasi Node di sekolah ini.</div></div>
        <div class="d-flex gap-2">
          <form method="POST" action="{{ route('admin.academic.student-attendance.settings.node.token', $school) }}" onsubmit="return confirm('Buat token baru? Token lama langsung tidak berlaku.')">@csrf<button class="btn btn-outline-secondary" type="submit">Buat token baru</button></form>
          <form method="POST" action="{{ route('admin.academic.student-attendance.settings.node.destroy', $school) }}" onsubmit="return confirm('Hapus koneksi Node untuk sekolah ini?')">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">Hapus koneksi</button></form>
        </div>
      </div>
    </div>
  @endif

  <script>
    (function () {
      var pw = document.getElementById('password');
      document.getElementById('pw-toggle').addEventListener('click', function () {
        pw.type = pw.type === 'password' ? 'text' : 'password';
        this.textContent = pw.type === 'password' ? 'Lihat' : 'Sembunyikan';
      });
      document.getElementById('pw-generate').addEventListener('click', function () {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        var bytes = new Uint32Array(20); crypto.getRandomValues(bytes);
        pw.value = Array.from(bytes, function (b) { return chars[b % chars.length]; }).join('');
        pw.type = 'text'; document.getElementById('pw-toggle').textContent = 'Sembunyikan';
      });
    })();
  </script>
@endsection
