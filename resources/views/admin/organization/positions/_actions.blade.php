<a href="{{ route('admin.organization.positions.edit', $position) }}" class="btn btn-sm btn-outline-primary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
<form method="POST" action="{{ route('admin.organization.positions.toggle', $position) }}" class="d-inline">
  @csrf @method('PATCH')
  <button type="submit" class="btn btn-sm {{ $position->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">{{ $position->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
</form>
