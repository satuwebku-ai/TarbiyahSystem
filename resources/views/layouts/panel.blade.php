<!DOCTYPE html>
<html lang="id">
<head>
  @include('partials.head')
  <title>@yield('title') — TarbiyahSystem</title>
</head>
<body class="TarbiyahSystem-body">
@include('partials.theme-boot')

<aside id="sidebar" class="position-fixed top-0 start-0 bottom-0 d-flex flex-column border-end border-white border-opacity-10 bg-sidebar" style="width:272px;z-index:1040">
  <div class="d-flex align-items-center gap-3 px-4 border-bottom border-white border-opacity-10 flex-shrink-0" style="height:64px">
    <div class="rounded-3 bg-accent d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M13 2 3 14h7l-1 8 11-12h-7l1-8z"/></svg>
    </div>
    <span class="text-white fw-bold text-nowrap" style="font-size:15px">@yield('brand', 'TarbiyahSystem')</span>
  </div>
  <nav class="flex-grow-1 overflow-y-auto px-3 py-4">
    <ul class="nav flex-column gap-1" style="font-size:13.5px">
      @yield('menu')
    </ul>
  </nav>
</aside>
<div id="sidebarBackdrop"></div>

<div id="main" class="main-shifted flex-grow-1 min-vh-100 d-flex flex-column" style="padding-top:64px;overflow-x:hidden;min-width:0">
  <header id="topbar" class="d-flex align-items-center justify-content-between px-4 position-fixed top-0 end-0 bg-topbar" style="height:64px;left:272px;z-index:1041">
    <button id="mobileMenuBtn" class="topbar-icon-btn btn align-items-center justify-content-center" style="display:none" aria-label="Menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="ms-auto d-flex align-items-center gap-3 text-white">
      <button id="themeToggleBtn" class="topbar-icon-btn btn d-flex align-items-center justify-content-center" title="Tema gelap/terang" type="button">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
      </button>
      <div class="text-end lh-sm d-none d-sm-block">
        <div class="fw-semibold" style="font-size:13px">{{ auth()->user()->name }}</div>
        <div class="text-white-50" style="font-size:11px">{{ auth()->user()->email }}</div>
      </div>
      <form method="POST" action="@yield('logout_url')">
        @csrf
        <button class="btn btn-sm btn-outline-light" type="submit">Keluar</button>
      </form>
    </div>
  </header>

  <main class="flex-grow-1 p-4 d-flex flex-column gap-3">
    @include('partials.flash')
    @yield('content')
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
  // Sidebar mobile
  const sb = document.getElementById('sidebar'), bd = document.getElementById('sidebarBackdrop');
  document.getElementById('mobileMenuBtn').addEventListener('click', () => { sb.classList.add('mobile-open'); bd.classList.add('visible'); });
  bd.addEventListener('click', () => { sb.classList.remove('mobile-open'); bd.classList.remove('visible'); });
  // Tema gelap/terang
  document.getElementById('themeToggleBtn').addEventListener('click', () => {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (dark) document.documentElement.removeAttribute('data-theme'); else document.documentElement.setAttribute('data-theme', 'dark');
    try { localStorage.setItem('TarbiyahSystem-theme', dark ? 'light' : 'dark'); } catch (e) {}
  });
</script>
</body>
</html>
