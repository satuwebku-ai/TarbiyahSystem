<!DOCTYPE html>
<html lang="id">
<head>
  @include('partials.head')
  <title>{{ config('app.name') }}</title>
</head>
<body class="TarbiyahSystem-body">
@include('partials.theme-boot')
<div class="min-vh-100 d-flex align-items-center justify-content-center bg-sidebar p-3">
  <div class="text-center text-white">
    <h1 class="fw-bold mb-2">{{ config('app.name') }}</h1>
    <p class="text-white-50 mb-4">Pilih area masuk</p>
    <div class="d-flex flex-wrap gap-2 justify-content-center">
      <a class="btn btn-primary" href="{{ route('pelajar.login') }}">Login Pelajar</a>
      <a class="btn btn-outline-light" href="{{ route('admin.login') }}">Login Pengelola</a>
    </div>
  </div>
</div>
</body>
</html>
