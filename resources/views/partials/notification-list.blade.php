<div class="card border-0 shadow-sm">
  <div class="list-group list-group-flush">
    @forelse ($notifications as $n)
      @php $unread = is_null($n->read_at); @endphp
      <a href="{{ route($prefix.'notifications.open', $n->id) }}"
         class="list-group-item list-group-item-action d-flex gap-3 align-items-start {{ $unread ? 'fw-semibold' : '' }}">
        <span class="mt-1 text-primary"><x-icon :name="$n->data['icon'] ?? 'info'" size="18"/></span>
        <span class="flex-grow-1" style="min-width:0">
          <span class="d-block">{{ $n->data['title'] ?? '' }}</span>
          <span class="d-block small text-secondary fw-normal">{{ $n->data['body'] ?? '' }}</span>
          <span class="d-block small text-secondary fw-normal">
            {{ \App\Notifications\WorkflowNotification::kindLabel($n->data['kind'] ?? null) }}
            · {{ \App\Helpers\AttendanceClock::toLocal($n->created_at)->format('d M Y H:i') }}
          </span>
        </span>
        @if ($unread)<span class="badge bg-primary rounded-pill align-self-center">Baru</span>@endif
      </a>
    @empty
      <div class="empty-state py-5 text-center">{{ $filter === 'unread' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' }}</div>
    @endforelse
  </div>
  @if ($notifications->hasPages())<div class="card-footer bg-transparent">{{ $notifications->links() }}</div>@endif
</div>
