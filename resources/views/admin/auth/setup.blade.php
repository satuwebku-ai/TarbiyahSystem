@extends('layouts.auth')
@section('title', 'Pengaturan Awal')
@section('subtitle', 'Buat akun Super Admin pertama')
@section('content')
  <div class="alert alert-info small">
    Buat akun pengelola awal untuk mengamankan panel. Halaman ini hanya tersedia saat belum ada akun pengelola dan aplikasi berjalan di lingkungan pengembangan.
  </div>
  <form method="POST" action="{{ route('admin.setup.store') }}">
    @csrf
    <div class="mb-3">
      <label for="name" class="form-label">Nama lengkap</label>
      <input id="name" name="name" value="{{ old('name') }}" required autofocus maxlength="120" class="form-control @error('name') is-invalid @enderror">
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input id="username" name="username" value="{{ old('username') }}" required autocomplete="username" maxlength="50" pattern="[A-Za-z0-9._-]+" class="form-control @error('username') is-invalid @enderror">
      @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label for="email" class="form-label">Email</label>
      <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" maxlength="255" class="form-control @error('email') is-invalid @enderror">
      @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Kata sandi</label>
      <input id="password" type="password" name="password" required autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
      <div class="form-text">Kata sandi harus memenuhi kebijakan keamanan sistem.</div>
      @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-4">
      <label for="password_confirmation" class="form-label">Ulangi kata sandi</label>
      <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
    </div>
    <button type="submit" class="btn btn-primary w-100">Buat Super Admin</button>
    <div class="text-center mt-3 small"><a href="{{ route('admin.login') }}" class="text-decoration-none">Kembali ke login</a></div>
  </form>
@endsection
