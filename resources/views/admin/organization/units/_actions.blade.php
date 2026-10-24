<a href="{{ route('admin.organization.units.show', $unit) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
@if($canManage)
  <a href="{{ route('admin.organization.units.edit', $unit) }}" class="btn btn-sm btn-outline-primary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
@endif
