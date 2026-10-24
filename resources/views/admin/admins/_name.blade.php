<div class="d-flex align-items-center gap-2">
  <span class="avatar avatar-sm">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($a->name, 0, 1)) }}</span>
  <div><div class="fw-semibold">{{ $a->name }}</div><div class="text-secondary small">{{ $a->username ? '@' . $a->username . ' · ' : '' }}{{ $a->email }}</div></div>
</div>
