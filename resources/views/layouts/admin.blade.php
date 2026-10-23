<!DOCTYPE html>
<html lang="id">
<head>
  @include('partials.head')
  <title>@yield('title') — {{ config('app.name', 'TarbiyahSystem') }}</title>
  {{-- cegah kedip: terapkan status sidebar ciut sebelum halaman tampil --}}
  <script>try{ if (localStorage.getItem('TarbiyahSystem-sidebar') === 'collapsed' && innerWidth >= 992 && localStorage.getItem('TarbiyahSystem-layout') !== 'horizontal') document.documentElement.classList.add('sidebar-pre-collapsed'); }catch(e){}</script>
  <noscript><style>#preloader{display:none!important}</style></noscript>
</head>
<body class="TarbiyahSystem-body">
<script>try{ if (localStorage.getItem('TarbiyahSystem-layout') === 'horizontal') document.body.classList.add('mode-horizontal'); }catch(e){}</script>
@include('partials.theme-boot')

<div id="preloader" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
  <div class="spinner-border text-light" role="status" style="width:2.5rem;height:2.5rem"><span class="visually-hidden">Memuat…</span></div>
</div>

@include('partials.admin.sidebar')
<div id="sidebarBackdrop"></div>

<div id="main" class="main-shifted flex-grow-1 min-vh-100 d-flex flex-column" style="padding-top:64px;overflow-x:hidden;min-width:0">
  @include('partials.admin.topbar')

  <main class="flex-grow-1 p-4 d-flex flex-column gap-3">
    @unless (trim($__env->yieldContent('hide_header')))
      <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <h4 class="page-title-gradient d-inline-block mb-1">@yield('title')</h4>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Beranda</a></li>
              @hasSection('parent')
                <li class="breadcrumb-item"><a href="@yield('parent_url')">@yield('parent')</a></li>
              @endif
              <li class="breadcrumb-item active" aria-current="page">@yield('title')</li>
            </ol>
          </nav>
        </div>
        <div class="d-flex gap-2">@yield('actions')</div>
      </div>
    @endunless

    @yield('content')
  </main>

  <footer class="footer-panel d-flex flex-wrap justify-content-between gap-2">
    <span>&copy; {{ date('Y') }} {{ config('app.name', 'TarbiyahSystem') }}. Hak cipta dilindungi.</span>
    <span>Panel Pengelola</span>
  </footer>
</div>

@include('partials.admin.theme-settings')

<div id="sidebarTooltip"></div>
<div id="sidebarFlyout"></div>
<div id="toastWrap" class="lm-toast-wrap pos-top-end" aria-live="polite"></div>
<div id="flashData" hidden
     data-success="{{ session('success') ?? session('status') }}"
     data-error="{{ session('error') }}"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="{{ asset('assets/TarbiyahSystem/panel.js') }}"></script>
@stack('scripts')
</body>
</html>
