<a href="{{ route('admin.academic.students.show', $student) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
@can('education.students.manage')
  <a href="{{ route('admin.academic.students.edit', $student) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
@endcan
