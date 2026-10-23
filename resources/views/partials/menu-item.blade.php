@php $active = request()->routeIs($pattern); @endphp
<li>
  <a href="{{ $href }}"
     class="nav-item-link {{ $active ? 'active text-white' : 'text-white-50' }} d-flex align-items-center gap-3 px-3 py-2 rounded-3 text-decoration-none">
    <span class="label-text text-nowrap">{{ $label }}</span>
  </a>
</li>
