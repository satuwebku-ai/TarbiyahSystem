<a href="{{ route('admin.employees.types.edit', $type) }}" class="btn btn-sm btn-outline-secondary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
<form method="POST" action="{{ route('admin.employees.types.toggle', $type) }}" class="d-inline">
  @csrf @method('PATCH')
  <button class="btn btn-sm {{ $type->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" type="submit">{{ $type->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
</form>
