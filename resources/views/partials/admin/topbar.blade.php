@php
  $me = auth()->user();
  $initials = \Illuminate\Support\Str::of($me->name)->explode(' ')->filter()->take(2)->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->implode('');
  $initials = \Illuminate\Support\Str::upper($initials ?: 'A');
  $roleLabel = $me->roles->pluck('label')->first() ?? 'Pengelola';
@endphp
<header id="topbar" class="d-flex align-items-center justify-content-between gap-3 px-4 position-fixed top-0 end-0 bg-topbar" style="height:64px;z-index:1041">
  <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
    <button id="mobileMenuBtn" type="button" class="topbar-icon-btn btn align-items-center justify-content-center" style="display:none" aria-label="Buka menu"><x-icon name="menu"/></button>
    <button id="collapseBtn" type="button" class="topbar-icon-btn btn d-flex align-items-center justify-content-center" title="Ciutkan / lebarkan sidebar" aria-label="Ciutkan sidebar"><x-icon name="sidebar"/></button>

    <div id="topbarBrand" class="align-items-center gap-2 text-white fw-bold me-2" style="display:none">
      <span class="rounded-3 bg-accent d-inline-flex align-items-center justify-content-center" style="width:30px;height:30px"><x-icon name="bolt" size="16" class="text-white"/></span>
      <span>{{ config('app.name', 'TarbiyahSystem') }}</span>
    </div>

    <div id="searchWrapper" class="ms-1">
      <x-icon name="search" class="search-icon"/>
      <input id="searchInput" type="search" class="form-control form-control-sm" placeholder="Cari menu…  ( / )" autocomplete="off" aria-label="Cari menu">
      <div id="searchDropdown" role="listbox"></div>
    </div>
  </div>

  <div class="d-flex align-items-center gap-1 text-white">
    <button id="layoutToggleBtn" type="button" title="Ganti tata letak menu"><x-icon name="layout"/><span id="layoutToggleLabel" class="d-none d-xl-inline">Horizontal</span></button>
    <button type="button" class="topbar-icon-btn btn d-flex align-items-center justify-content-center" data-bs-toggle="offcanvas" data-bs-target="#themeSettings" title="Pengaturan tema" aria-label="Pengaturan tema"><x-icon name="palette"/></button>
    <button id="themeToggleBtn" type="button" class="topbar-icon-btn btn d-flex align-items-center justify-content-center" title="Tema gelap/terang" aria-label="Tema gelap/terang">
      <x-icon id="themeIconMoon" name="moon"/>
      <x-icon id="themeIconSun" name="sun" style="display:none"/>
    </button>

    @php
      $unread = $me->unreadNotifications()->count();
      $latest = $me->notifications()->latest()->limit(5)->get();
    @endphp
    <div class="dropdown">
      <button type="button" class="topbar-icon-btn btn d-flex align-items-center justify-content-center position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi" aria-label="Notifikasi{{ $unread ? ', '.$unread.' belum dibaca' : '' }}">
        <x-icon name="bell"/>
        @if ($unread)<span class="position-absolute badge rounded-pill bg-danger" style="top:2px;right:0;font-size:.6rem">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
      </button>
      <ul class="dropdown-menu dropdown-menu-end p-0" style="min-width:330px;max-width:92vw">
        <li class="px-3 py-2 d-flex justify-content-between align-items-center border-bottom">
          <span class="fw-semibold">Notifikasi</span>
          <a class="small" href="{{ route('admin.notifications.index') }}">Lihat semua</a>
        </li>
        @forelse ($latest as $n)
          <li>
            <a class="dropdown-item text-wrap py-2 {{ $n->read_at ? '' : 'fw-semibold' }}" href="{{ route('admin.notifications.open', $n->id) }}">
              <span class="d-block">{{ $n->data['title'] ?? '' }}</span>
              <span class="d-block small text-secondary fw-normal">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 90) }}</span>
            </a>
          </li>
        @empty
          <li class="px-3 py-4 text-center text-secondary small">Belum ada notifikasi.</li>
        @endforelse
      </ul>
    </div>

    <div class="dropdown ms-2">
      <button class="user-trigger" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="avatar avatar-sm">{{ $initials }}</span>
        <span class="d-none d-md-block text-start"><span class="u-name d-block">{{ $me->name }}</span><span class="u-role d-block">{{ $roleLabel }}</span></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px">
        <li class="dropdown-header-user">
          <div class="fw-semibold">{{ $me->name }}</div>
          <div class="text-secondary small text-truncate">{{ $me->email }}</div>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="{{ route('admin.profile.edit') }}"><x-icon name="user"/>Profil Saya</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="dropdown-item text-danger"><x-icon name="logout"/>Keluar</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</header>
