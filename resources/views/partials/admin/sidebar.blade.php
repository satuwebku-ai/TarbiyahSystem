@php
  $me = auth()->user();
  $menu = collect(config('admin_menu'))->map(function ($section) use ($me) {
      $section['items'] = collect($section['items'])->map(function ($item) use ($me) {
          if (isset($item['children'])) {
              $item['children'] = collect($item['children'])->filter(fn ($c) => empty($c['can']) || $me->can($c['can']))->values()->all();
              return $item['children'] ? $item : null;
          }
          return (empty($item['can']) || $me->can($item['can'])) ? $item : null;
      })->filter()->values()->all();
      return $section;
  })->filter(fn ($s) => $s['items'])->values();
@endphp

<aside id="sidebar" class="position-fixed top-0 start-0 bottom-0 d-flex flex-column border-end border-white border-opacity-10 bg-sidebar" style="width:272px;z-index:1040">
  <div id="brand-area" class="d-flex align-items-center gap-3 px-4 border-bottom border-white border-opacity-10 flex-shrink-0" style="height:64px">
    <div class="rounded-3 bg-accent d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px">
      <x-icon name="bolt" size="18" class="text-white"/>
    </div>
    <span class="brand-text text-white fw-bold text-nowrap lh-sm" style="font-size:15px">{{ config('app.name', 'TarbiyahSystem') }}<small>Pengelola</small></span>
  </div>

  <nav class="sidebar-scroll flex-grow-1 overflow-y-auto px-3 py-4">
    @foreach ($menu as $section)
      <p class="menu-eyebrow">{{ $section['title'] }}</p>
      <ul class="nav flex-column gap-1" style="font-size:13.5px">
        @foreach ($section['items'] as $item)
          @if (isset($item['children']))
            @php
              $open = collect($item['children'])->contains(fn ($c) => request()->routeIs($c['active']) && ! request()->routeIs($c['except'] ?? []));
              $cls  = $open ? 'active text-white' : 'text-white-50';
            @endphp
            <li class="menu-item {{ $open ? 'open' : '' }}">
              <button type="button" data-tip="{{ $item['label'] }}"
                      class="menu-trigger nav-item-link {{ $cls }} w-100 border-0 bg-transparent d-flex align-items-center justify-content-between gap-3 px-3 py-2 rounded-3">
                <span class="d-flex align-items-center gap-3"><x-icon :name="$item['icon']"/><span class="label-text text-nowrap">{{ $item['label'] }}</span></span>
                <span><x-icon name="chevron" class="chevron"/></span>
              </button>
              <ul class="submenu nav flex-column {{ $open ? 'open' : '' }}">
                @foreach ($item['children'] as $c)
                  <li><a class="nav-link {{ request()->routeIs($c['active']) && ! request()->routeIs($c['except'] ?? []) ? 'active' : '' }}" href="{{ route($c['route']) }}">{{ $c['label'] }}</a></li>
                @endforeach
              </ul>
            </li>
          @else
            @php $active = request()->routeIs($item['active']) && ! request()->routeIs($item['except'] ?? []); @endphp
            <li class="menu-item">
              <a href="{{ route($item['route']) }}" data-tip="{{ $item['label'] }}"
                 class="nav-item-link {{ $active ? 'active text-white' : 'text-white-50' }} d-flex align-items-center justify-content-between gap-3 px-3 py-2 rounded-3 text-decoration-none">
                <span class="d-flex align-items-center gap-3"><x-icon :name="$item['icon']"/><span class="label-text text-nowrap">{{ $item['label'] }}</span></span>
              </a>
            </li>
          @endif
        @endforeach
      </ul>
    @endforeach
  </nav>

  <div class="p-3 flex-shrink-0">
    <div class="help-box rounded-3 border p-3">
      <div class="d-flex align-items-center gap-2 mb-1"><x-icon name="help" size="16" class="text-white"/><span class="help-title">Butuh bantuan?</span></div>
      <div class="help-text">Hubungi Super Admin untuk perubahan hak akses.</div>
    </div>
  </div>
</aside>
