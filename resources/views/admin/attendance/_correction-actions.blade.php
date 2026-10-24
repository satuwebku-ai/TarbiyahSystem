@if($c->status === \App\Enums\CorrectionStatus::Pending)
  <form method="POST" action="{{ route('admin.attendance.corrections.approve', $c) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-success" type="submit">Setujui</button></form>
  <form method="POST" action="{{ route('admin.attendance.corrections.reject', $c) }}" class="d-inline-flex gap-1 mt-1">@csrf @method('PATCH')
    <input name="review_note" class="form-control form-control-sm" placeholder="Alasan penolakan" maxlength="500" required aria-label="Alasan penolakan">
    <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button>
  </form>
@else
  <span class="badge {{ $c->status->badge() }}">{{ $c->status->label() }}</span>
@endif
