@extends('admin._layout')
@section('title', 'Akun Pengelola')
@section('actions')
  @can('admins.create') <a href="{{ route('admin.admins.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" class="me-1"/>Tambah Akun</a> @endcan
@endsection
@section('content')
  <div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Nama</th><th>Role</th><th>Cakupan unit</th><th>Status</th><th>Login terakhir</th><th></th></tr></thead>
      <tbody>
      @forelse ($admins as $a)
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="avatar avatar-sm">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($a->name, 0, 1)) }}</span>
              <div><div class="fw-semibold">{{ $a->name }}</div><div class="text-secondary small">{{ $a->username ? '@' . $a->username . ' · ' : '' }}{{ $a->email }}</div></div>
            </div>
          </td>
          <td>@forelse($a->roles as $r)<span class="badge badge-soft-primary">{{ $r->label }}</span> @empty <span class="text-secondary">—</span> @endforelse</td>
          <td class="small">
            @php $names = $unitNames[$a->id] ?? []; @endphp
            @if($a->isSuperAdmin())
              <span class="badge badge-soft-warning">Semua unit</span>
            @elseif(count($names))
              @foreach(array_slice($names, 0, 2) as $n)<span class="badge badge-soft-secondary">{{ $n }}</span> @endforeach
              @if(count($names) > 2)<span class="text-secondary">+{{ count($names) - 2 }} lagi</span>@endif
            @else
              <span class="text-secondary">—</span>
            @endif
          </td>
          <td>{!! $a->is_active ? '<span class="badge badge-soft-success">Aktif</span>' : '<span class="badge badge-soft-secondary">Nonaktif</span>' !!}</td>
          <td class="small">{{ $a->last_login_at?->format('d M Y H:i') ?? '—' }}</td>
          <td class="text-end text-nowrap">
            @can('admins.update') <a href="{{ route('admin.admins.edit', $a) }}" class="btn btn-sm btn-outline-primary">Ubah</a> @endcan
            @can('admins.delete')
              @if(! $a->is(auth()->user()))
                <form method="POST" action="{{ route('admin.admins.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Hapus akun ini?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Hapus</button>
                </form>
              @endif
            @endcan
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty-state">Belum ada akun pengelola.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div></div>
  {{ $admins->links('pagination::bootstrap-5') }}
@endsection
