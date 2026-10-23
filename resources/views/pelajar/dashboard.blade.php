@extends('pelajar._layout')
@section('title', 'Dashboard')
@section('content')
  @php $me = auth()->user(); @endphp

  <div class="hero-card bg-page-hero text-white p-4">
    <div class="position-relative d-flex flex-wrap align-items-center gap-3" style="z-index:1">
      <span class="avatar avatar-lg bg-white bg-opacity-25">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($me->name, 0, 1)) }}</span>
      <div class="flex-grow-1">
        <h4 class="fw-bold mb-1">Halo, {{ $me->name }}</h4>
        <div class="text-white-75 small">Selamat datang di area pelajar Anda.</div>
      </div>
      <a href="{{ route('pelajar.profile.edit') }}" class="btn btn-light btn-sm">Kelola akun</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Ringkasan akun</div>
        <div class="card-body py-2">
          <div class="info-row"><span class="k">Nama</span><span class="fw-semibold">{{ $me->name }}</span></div>
          <div class="info-row"><span class="k">NIS</span><span>{{ $me->nis ?? '—' }}</span></div>
          <div class="info-row"><span class="k">Email</span><span>{{ $me->email ?? '—' }}</span></div>
          <div class="info-row"><span class="k">Status</span>
            <span>{!! $me->is_active ? '<span class="badge badge-soft-success">Aktif</span>' : '<span class="badge badge-soft-secondary">Nonaktif</span>' !!}</span></div>
          <div class="info-row"><span class="k">Terdaftar sejak</span><span>{{ $me->created_at?->format('d M Y') ?? '—' }}</span></div>
          <div class="info-row"><span class="k">Login terakhir</span><span>{{ $me->last_login_at?->format('d M Y H:i') ?? '—' }}</span></div>
        </div>
      </div>
    </div>

    <div class="col-lg-5 d-flex flex-column gap-3">
      <a href="{{ route('pelajar.profile.edit') }}" class="quick-card card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon"><x-icon name="user"/></span>
          <div><div class="fw-semibold">Ubah profil</div><div class="text-secondary small">Perbarui nama dan email Anda.</div></div>
        </div>
      </a>
      <a href="{{ route('pelajar.profile.edit') }}#password" class="quick-card card border-0 shadow-sm">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="stat-icon"><x-icon name="lock"/></span>
          <div><div class="fw-semibold">Ganti kata sandi</div><div class="text-secondary small">Jaga keamanan akun Anda.</div></div>
        </div>
      </a>
    </div>
  </div>
@endsection
