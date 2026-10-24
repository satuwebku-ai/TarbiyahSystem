<a href="{{ route('admin.organization.assignments.edit', $assignment) }}" class="btn btn-sm btn-outline-primary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
@if($assignment->status === 'active')
  <form method="POST" action="{{ route('admin.organization.assignments.end', $assignment) }}" class="d-inline">
    @csrf @method('PATCH')
    <input type="hidden" name="end_date" value="{{ max(today()->toDateString(), $assignment->start_date->toDateString()) }}">
    <button class="btn btn-sm btn-outline-warning" type="submit">Akhiri</button>
  </form>
@endif
