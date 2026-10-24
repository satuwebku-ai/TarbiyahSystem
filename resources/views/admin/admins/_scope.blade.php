<span class="small">
@if($a->isSuperAdmin())
  <span class="badge badge-soft-warning">Semua unit</span>
@elseif(count($names))
  @foreach(array_slice($names, 0, 2) as $n)<span class="badge badge-soft-secondary">{{ $n }}</span> @endforeach
  @if(count($names) > 2)<span class="text-secondary">+{{ count($names) - 2 }} lagi</span>@endif
@else
  <span class="text-secondary">—</span>
@endif
</span>
