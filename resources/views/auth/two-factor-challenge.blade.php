@extends('layouts.auth')
@section('title', 'Verifikasi Dua Faktor')
@section('subtitle', $subtitle)
@section('content')
  <p class="text-secondary small">Buka aplikasi autentikator di ponsel Anda (Google Authenticator, Microsoft Authenticator, Authy, dsb.), lalu masukkan kode 6 digit untuk akun ini.</p>
  <form method="POST" action="{{ $action }}" novalidate>
    @csrf
    <div class="mb-3">
      <label class="form-label" for="code">Kode autentikasi</label>
      <input type="text" id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="text"
             placeholder="123456" class="form-control form-control-lg text-center @error('code') is-invalid @enderror">
      @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
      <div class="form-text">Ponsel hilang? Masukkan salah satu <strong>kode pemulihan</strong> (contoh: <code>abcde-fghij</code>) sebagai gantinya; tiap kode hanya bisa dipakai sekali.</div>
    </div>
    <button class="btn btn-primary w-100" type="submit">Verifikasi</button>
    <div class="text-center mt-3 small"><a href="{{ $loginUrl }}" class="text-decoration-none">Kembali ke login</a></div>
  </form>
@endsection
