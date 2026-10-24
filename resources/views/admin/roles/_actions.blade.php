@can('roles.update') <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">Ubah</a> @endcan
@can('roles.delete')
  @unless($role->is_system)
    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" data-confirm="Hapus role ini?">
      @csrf @method('DELETE')
      <button class="btn btn-sm btn-outline-danger">Hapus</button>
    </form>
  @endunless
@endcan
