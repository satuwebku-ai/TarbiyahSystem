<a href="{{ route('admin.academic.schools.show', $school) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
@can('education.schools.manage')
  <a href="{{ route('admin.academic.schools.edit', $school) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
@endcan
