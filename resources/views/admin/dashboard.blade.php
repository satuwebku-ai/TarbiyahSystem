@extends('admin._layout')
@section('title', 'Dashboard')
@section('hide_header', '1')
@section('content')
  @php $me = auth()->user(); @endphp

  {{-- Hero sambutan --}}
  <div class="hero-card bg-page-hero text-white p-4 p-md-5">
    <div class="position-relative" style="z-index:1">
      <div class="text-white-75 small mb-1">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</div>
      <h3 class="fw-bold mb-2">Selamat datang, {{ $me->name }}</h3>
      <p class="text-white-75 mb-3" style="max-width:560px">
        Anda masuk sebagai <strong>{{ $me->roles->pluck('label')->join(', ') ?: 'Pengelola' }}</strong>.
        Login terakhir: {{ $me->last_login_at?->format('d M Y H:i') ?? 'baru pertama kali' }}.
      </p>
      <div class="d-flex flex-wrap gap-2">
        @can('admins.create') <a href="{{ route('admin.admins.create') }}" class="btn btn-light btn-sm">Tambah akun pengelola</a> @endcan
        @can('roles.view') <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-light btn-sm">Kelola role</a> @endcan
      </div>
    </div>
  </div>

  {{-- Statistik --}}
  <div class="row g-3">
    @foreach ([
      ['Akun Pengelola', $totalAdmins,  'users',  'card-stat-blue'],
      ['Pengelola Aktif', $activeAdmins, 'check',  'card-stat-emerald'],
      ['Role',            $totalRoles,   'shield', 'card-stat-amber'],
      ['Pelajar',         $totalPelajar, 'user',   'card-stat-rose'],
    ] as [$label, $val, $icon, $cls])
      <div class="col-sm-6 col-xl-3">
        <div class="card {{ $cls }} border shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="stat-icon"><x-icon :name="$icon"/></span>
            <div>
              <div class="text-secondary small">{{ $label }}</div>
              <div class="fs-3 fw-bold lh-1 mt-1">{{ number_format($val, 0, ',', '.') }}</div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3">
    @can('admins.view')
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Aktivitas login pengelola</span>
            <a href="{{ route('admin.admins.index') }}" class="small text-decoration-none">Lihat semua</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th>Pengelola</th><th>Role</th><th>Login terakhir</th></tr></thead>
              <tbody>
              @forelse ($recentAdmins as $a)
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <span class="avatar avatar-sm">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($a->name, 0, 1)) }}</span>
                      <div><div class="fw-semibold">{{ $a->name }}</div><div class="text-secondary small">{{ $a->email }}</div></div>
                    </div>
                  </td>
                  <td>@forelse($a->roles as $r)<span class="badge badge-soft-primary">{{ $r->label }}</span> @empty <span class="text-secondary">—</span> @endforelse</td>
                  <td class="small">{{ $a->last_login_at?->diffForHumans() ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="empty-state">Belum ada data.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endcan

    <div class="@can('admins.view') col-lg-5 @else col-12 @endcan">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Pelajar terbaru</div>
        <div class="card-body p-0">
          @forelse ($newPelajar as $m)
            <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom">
              <span class="avatar avatar-sm">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($m->name, 0, 1)) }}</span>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate">{{ $m->name }}</div>
                <div class="text-secondary small text-truncate">{{ $m->nis ?? $m->email }}</div>
              </div>
              <span class="small text-secondary text-nowrap">{{ $m->created_at?->diffForHumans() }}</span>
            </div>
          @empty
            <div class="empty-state">Belum ada pelajar terdaftar.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
