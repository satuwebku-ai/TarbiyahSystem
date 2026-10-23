<p class="text-secondary small">Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.</p>
<form method="POST" action="{{ $action }}" novalidate>
  @csrf
  <div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
           class="form-control @error('email') is-invalid @enderror">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <button class="btn btn-primary w-100" type="submit">Kirim tautan reset</button>
  <div class="text-center mt-3 small"><a href="{{ $login }}" class="text-decoration-none">Kembali ke login</a></div>
</form>
