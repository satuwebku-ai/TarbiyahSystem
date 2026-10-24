@switch($col)
  @case('name')
    <div class="fw-semibold">{{ $device->name }}</div>
    <div class="small text-secondary">SN {{ $device->serial_number }}@if($device->location) · {{ $device->location }}@endif</div>
    @break
  @case('connection')
    <span class="badge {{ $device->isOnline() ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $device->isOnline() ? 'Terhubung' : 'Tidak terhubung' }}</span>
    <div class="small text-secondary">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Belum pernah' }}@if($device->last_ip) · {{ $device->last_ip }}@endif</div>
    @break
  @case('logs')
    {{ number_format($device->total_logs) }}
    @if($device->unmapped_count)
      <a href="{{ route('admin.academic.fingerprint.logs.index', ['device_id' => $device->id, 'result' => 'unmapped']) }}" class="badge badge-soft-danger text-decoration-none">{{ $device->unmapped_count }} PIN belum terpetakan</a>
    @endif
    @if($device->last_log_at)<div class="small text-secondary">Terakhir {{ \App\Helpers\AttendanceClock::toLocal($device->last_log_at)->format('d M H:i') }}</div>@endif
    @break
  @case('status')
    @if($device->isPending())<span class="badge badge-soft-warning">Baru terdeteksi</span>
    @else<span class="badge {{ $device->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $device->is_active ? 'Aktif' : 'Nonaktif' }}</span>
      @if($device->is_active && ! $device->hasIpRestriction())<div><span class="badge badge-soft-{{ $device->blockedForMissingIpRestriction() ? 'danger' : 'warning' }}">{{ $device->blockedForMissingIpRestriction() ? 'Ditolak: IP belum dibatasi' : 'IP belum dibatasi' }}</span></div>@endif
    @endif
    @break
  @case('action')
    @if($canManage)
      <a href="{{ route('admin.academic.fingerprint.devices.edit', $device) }}" class="btn btn-sm btn-outline-primary">{{ $device->isPending() ? 'Atur & aktifkan' : 'Ubah' }}</a>
      @unless($device->isPending())
        <form method="POST" action="{{ route('admin.academic.fingerprint.devices.toggle', $device) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
      @endunless
      @unless($device->is_active)
        <form method="POST" action="{{ route('admin.academic.fingerprint.devices.destroy', $device) }}" class="d-inline" data-confirm="Hapus mesin ini beserta seluruh log ketukannya? Absensi yang sudah tercatat tidak ikut terhapus.">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form>
      @endunless
    @endif
    @break
@endswitch
