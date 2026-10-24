@can('admins.update') <a href="{{ route('admin.admins.edit', $a) }}" class="btn btn-sm btn-outline-primary">Ubah</a> @endcan
@can('admins.delete')
  @if(! $a->is($me))
    <form method="POST" action="{{ route('admin.admins.destroy', $a) }}" class="d-inline" data-confirm="Hapus akun ini?">
      @csrf @method('DELETE')
      <button class="btn btn-sm btn-outline-danger">Hapus</button>
    </form>
  @endif
@endcan
