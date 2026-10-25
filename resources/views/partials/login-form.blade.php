<form method="POST" action="{{ $action }}" novalidate>
  @csrf
  <div class="mb-3">
    @php $field = $field ?? 'email'; @endphp
    <label class="form-label" for="{{ $field }}">{{ $label ?? 'Email' }}</label>
    <input type="{{ $field === 'email' ? 'email' : 'text' }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required autofocus autocomplete="username" @if($field === 'nis') inputmode="numeric" @endif
           class="form-control @error($field) is-invalid @enderror">
    @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="mb-3">
    <div class="d-flex justify-content-between">
      <label class="form-label" for="password">Kata sandi</label>
      @isset($forgot) <a href="{{ $forgot }}" class="small text-decoration-none">Lupa kata sandi?</a> @endisset
    </div>
    <input type="password" id="password" name="password" required autocomplete="current-password"
           class="form-control @error('password') is-invalid @enderror">
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="form-check mb-4">
    <input class="form-check-input" type="checkbox" name="remember" id="remember">
    <label class="form-check-label" for="remember">Ingat saya</label>
  </div>
  @include('partials.captcha', ['page' => 'login'])
  <button class="btn btn-primary w-100" type="submit">Masuk</button>
  @isset($register)
    <div class="text-center mt-3 small">Belum punya akun? <a href="{{ $register }}" class="text-decoration-none">Daftar</a></div>
  @endisset
</form>
