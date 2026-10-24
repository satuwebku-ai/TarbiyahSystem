<a href="{{ route('admin.programs.show', $program) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
@if(in_array($program->status, ['draft', 'rejected'], true))
  @can('programs.manage')
    <a href="{{ route('admin.programs.edit', $program) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
  @endcan
@endif
