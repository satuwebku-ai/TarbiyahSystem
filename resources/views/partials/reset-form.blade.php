<form method="POST" action="{{ $action }}" novalidate>
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required readonly
           class="form-control @error('email') is-invalid @enderror">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="mb-3">
    <label class="form-label" for="password">Kata sandi baru</label>
    <input type="password" id="password" name="password" required autofocus autocomplete="new-password"
           class="form-control @error('password') is-invalid @enderror">
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Minimal 8 karakter, mengandung huruf dan angka.</div>
  </div>
  <div class="mb-4">
    <label class="form-label" for="password_confirmation">Ulangi kata sandi</label>
    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
  </div>
  <button class="btn btn-primary w-100" type="submit">Simpan kata sandi</button>
</form>
