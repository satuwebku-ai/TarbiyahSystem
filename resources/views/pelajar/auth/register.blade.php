@extends('layouts.auth')
@section('title', 'Daftar Pelajar')
@section('subtitle', 'Buat akun pelajar')
@section('content')
<form method="POST" action="{{ route('pelajar.register.store') }}" novalidate>
  @csrf
  <div class="mb-3">
    <label class="form-label" for="name">Nama lengkap</label>
    <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
           class="form-control @error('name') is-invalid @enderror">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="mb-3">
    <label class="form-label" for="nis">NIS (Nomor Induk Pelajar)</label>
    <input id="nis" name="nis" value="{{ old('nis') }}" required inputmode="numeric" autocomplete="username"
           class="form-control @error('nis') is-invalid @enderror">
    @error('nis') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="mb-3">
    <label class="form-label" for="email">Email <span class="text-secondary">(opsional)</span></label>
    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email"
           class="form-control @error('email') is-invalid @enderror">
    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
  <div class="mb-3">
    <label class="form-label" for="password">Kata sandi</label>
    <input type="password" id="password" name="password" required autocomplete="new-password"
           class="form-control @error('password') is-invalid @enderror">
    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    <div class="form-text">Minimal 8 karakter, mengandung huruf dan angka.</div>
  </div>
  <div class="mb-4">
    <label class="form-label" for="password_confirmation">Ulangi kata sandi</label>
    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="form-control">
  </div>
  <button class="btn btn-primary w-100" type="submit">Daftar</button>
  <div class="text-center mt-3 small">Sudah punya akun? <a href="{{ route('pelajar.login') }}" class="text-decoration-none">Masuk</a></div>
</form>
@endsection
