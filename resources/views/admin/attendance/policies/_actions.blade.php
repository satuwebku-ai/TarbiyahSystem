@if($p->organizational_unit_id || $isUnrestricted)
  <a href="{{ route('admin.attendance.policies.edit', $p) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
  <form method="POST" action="{{ route('admin.attendance.policies.toggle', $p) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $p->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
@endif
