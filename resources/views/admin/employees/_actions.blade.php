<a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
@can('employees.update')<a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>@endcan
