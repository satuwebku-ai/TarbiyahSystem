@extends('admin._layout')
@section('title', 'Role & Permission')
@section('actions')
  @can('roles.create') <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" class="me-1"/>Tambah Role</a> @endcan
@endsection
@section('content')
  <div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Role</th><th>Kode</th><th class="text-center">Permission</th><th class="text-center">Akun</th><th></th></tr></thead>
      <tbody>
      @foreach ($roles as $role)
        <tr>
          <td>
            <div class="fw-semibold">{{ $role->label }} @if($role->is_system)<span class="badge badge-soft-secondary">sistem</span>@endif</div>
            <div class="text-secondary small">{{ $role->description }}</div>
          </td>
          <td><code>{{ $role->name }}</code></td>
          <td class="text-center">{{ $role->name === 'super-admin' ? 'Semua' : $role->permissions_count }}</td>
          <td class="text-center">{{ $role->admins_count }}</td>
          <td class="text-end text-nowrap">
            @can('roles.update') <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Ubah</a> @endcan
            @can('roles.delete')
              @unless($role->is_system)
                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" onsubmit="return confirm('Hapus role ini?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger">Hapus</button>
                </form>
              @endunless
            @endcan
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div></div>
@endsection
