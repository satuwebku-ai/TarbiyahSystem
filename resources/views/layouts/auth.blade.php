<!DOCTYPE html>
<html lang="id">
<head>
  @include('partials.head')
  <title>@yield('title') — TarbiyahSystem</title>
</head>
<body class="TarbiyahSystem-body">
@include('partials.theme-boot')
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3 bg-sidebar">
  <div class="card border-0 shadow-lg" style="width:100%;max-width:420px;border-radius:1rem">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-3 bg-accent d-flex align-items-center justify-content-center" style="width:40px;height:40px">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
        </div>
        <div>
          <div class="fw-bold">TarbiyahSystem</div>
          <div class="text-secondary small">@yield('subtitle')</div>
        </div>
      </div>

      @include('partials.flash')
      @yield('content')
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
