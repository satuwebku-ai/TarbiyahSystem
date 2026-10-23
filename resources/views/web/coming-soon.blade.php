<!DOCTYPE html>
<html lang="id">
<head>
  @include('partials.head')
  <title>{{ config('app.name') }} — Coming soon</title>
</head>
<body class="TarbiyahSystem-body">
@include('partials.theme-boot')
<div class="min-vh-100 d-flex align-items-center justify-content-center bg-sidebar p-3">
  <div class="text-center text-white">
    <h1 class="fw-bold mb-2">{{ config('app.name') }}</h1>
    <p class="display-6 fw-semibold mb-0">Coming soon</p>
  </div>
</div>
</body>
</html>
