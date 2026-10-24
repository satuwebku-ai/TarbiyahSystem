<!DOCTYPE html>
<html lang="id">
<head>
  @include('partials.head')
  <title>@yield('title') — {{ config('app.name', 'TarbiyahSystem') }}</title>
</head>
<body class="TarbiyahSystem-body d-flex flex-column min-vh-100">
@include('partials.theme-boot')
@php
  $me = auth()->user();
  $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($me->name, 0, 1));
  $links = [
    ['Dashboard',          'home', route('pelajar.dashboard'),    'pelajar.dashboard'],
    ['Absensi',            'calendar', route('pelajar.attendance'), 'pelajar.attendance'],
    ['Profil & Keamanan',  'user', route('pelajar.profile.edit'), 'pelajar.profile.*'],
  ];
@endphp

<nav class="pelajar-nav navbar navbar-expand-lg navbar-dark bg-topbar py-2">
  <div class="container-xl">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('pelajar.dashboard') }}">
      <span class="brand-mark"><x-icon name="bolt" size="18" class="text-white"/></span>
      <span>{{ config('app.name', 'TarbiyahSystem') }}</span>
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#pelajarMenu" aria-controls="pelajarMenu" aria-expanded="false" aria-label="Menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="pelajarMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-lg-1">
        @foreach ($links as [$label, $icon, $href, $pattern])
          <li class="nav-item">
            <a class="nav-link d-flex align-items-center gap-2 {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ $href }}"
               @if(request()->routeIs($pattern)) aria-current="page" @endif>
              <x-icon :name="$icon" size="16"/>{{ $label }}
            </a>
          </li>
        @endforeach
      </ul>

      <div class="d-flex align-items-center gap-2">
        <button id="pelajarThemeBtn" type="button" class="topbar-icon-btn btn d-flex align-items-center justify-content-center" title="Tema gelap/terang" aria-label="Tema gelap/terang">
          <x-icon id="themeIconMoon" name="moon"/>
          <x-icon id="themeIconSun" name="sun" style="display:none"/>
        </button>
        <div class="dropdown">
          <button class="user-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="avatar avatar-sm">{{ $initials }}</span>
            <span class="text-start"><span class="u-name d-block">{{ $me->name }}</span><span class="u-role d-block">Pelajar</span></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px">
            <li class="dropdown-header-user">
              <div class="fw-semibold">{{ $me->name }}</div>
              <div class="text-secondary small text-truncate">{{ $me->nis ?? $me->email }}</div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ route('pelajar.profile.edit') }}"><x-icon name="user"/>Profil & Keamanan</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="POST" action="{{ route('pelajar.logout') }}">
                @csrf
                <button type="submit" class="dropdown-item text-danger"><x-icon name="logout"/>Keluar</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav>

<main class="pelajar-main flex-grow-1 container-xl py-4 d-flex flex-column gap-3">
  @hasSection('title_visible')
    <div>
      <h4 class="page-title-gradient d-inline-block mb-0">@yield('title_visible')</h4>
      @hasSection('subtitle')<div class="text-secondary small">@yield('subtitle')</div>@endif
    </div>
  @endif
  @include('partials.flash')
  @yield('content')
</main>

<footer class="pelajar-footer container-xl py-3 d-flex flex-wrap justify-content-between gap-2 border-top">
  <span>&copy; {{ date('Y') }} {{ config('app.name', 'TarbiyahSystem') }}. Hak cipta dilindungi.</span>
  <span>Area Pelajar</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
  document.getElementById('pelajarThemeBtn').addEventListener('click', function () {
    var root = document.documentElement, dark = root.getAttribute('data-theme') === 'dark';
    if (dark) root.removeAttribute('data-theme'); else root.setAttribute('data-theme', 'dark');
    try { localStorage.setItem('TarbiyahSystem-theme', dark ? 'light' : 'dark'); } catch (e) {}
  });
</script>
</body>
</html>
