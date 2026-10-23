@php
  /** @var \App\Models\OrganizationalAssignment $assignment */
  $showPosition = $showPosition ?? true;
@endphp
<li class="org-tree-item" role="treeitem">
  <div class="org-tree-row org-pos-row {{ $row['match'] ? 'org-match' : '' }}">
    <span class="org-tree-toggle org-tree-leaf" aria-hidden="true"><span class="org-tree-dot"></span></span>
    <span class="org-tree-icon org-pos-icon"><x-icon name="user" size="15"/></span>
    <div class="org-tree-info">
      <div class="fw-semibold text-truncate">{{ $assignment->assignee_name }}</div>
      <div class="small text-secondary text-truncate">
        @if ($showPosition){{ $assignment->position?->name }} · @endif
        {{ $assignment->admin ? '@'.($assignment->admin->username ?: $assignment->admin->name) : 'Tanpa akun pengelola' }}
        @if (! $assignment->employee_id) · <span class="text-warning">Belum terhubung ke data pegawai</span> @endif
      </div>
    </div>
    <span class="small text-secondary text-nowrap d-none d-lg-inline org-period">
      {{ $assignment->start_date?->format('d M Y') }} — {{ $assignment->end_date?->format('d M Y') ?? 'sekarang' }}
    </span>
    <span class="badge org-tree-meta {{ $assignment->status === 'active' ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $assignment->status === 'active' ? 'Aktif' : 'Selesai' }}</span>
    <a href="{{ route('admin.organization.assignments.edit', $assignment) }}" class="btn btn-sm btn-light" aria-label="Ubah penugasan {{ $assignment->assignee_name }}">
      <x-icon name="edit" size="15"/>
    </a>
    @if ($assignment->status === 'active')
      <form method="POST" action="{{ route('admin.organization.assignments.end', $assignment) }}" class="d-none d-md-inline m-0">
        @csrf @method('PATCH')
        <input type="hidden" name="end_date" value="{{ max(today()->toDateString(), $assignment->start_date->toDateString()) }}">
        <button class="btn btn-sm btn-outline-warning" type="submit">Akhiri</button>
      </form>
    @endif
  </div>
</li>
