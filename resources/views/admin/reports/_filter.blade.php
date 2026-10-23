@php
  $action = $action ?? url()->current();
  $usesPeriod = $usesPeriod ?? true;
@endphp
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
      @if($usesPeriod)
        <div class="col-md-3 col-6">
          <label for="from" class="form-label small mb-1">Dari tanggal</label>
          <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control @error('from') is-invalid @enderror">
          @error('from') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3 col-6">
          <label for="to" class="form-label small mb-1">Sampai tanggal</label>
          <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control @error('to') is-invalid @enderror">
          @error('to') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
      @endif
      <div class="col-md-4">
        <label for="unit_id" class="form-label small mb-1">Unit</label>
        <select id="unit_id" name="unit_id" class="form-select @error('unit_id') is-invalid @enderror">
          <option value="">Semua unit dalam cakupan</option>
          @foreach($units as $unit)
            <option value="{{ $unit->id }}" @selected((string) $unitId === (string) $unit->id)>{{ $unit->name }}</option>
          @endforeach
        </select>
        <div class="form-text">Unit induk otomatis mencakup unit di bawahnya.</div>
      </div>
      <div class="col-auto d-flex gap-2 mb-4">
        <button class="btn btn-primary" type="submit">Tampilkan</button>
        <a href="{{ $action }}" class="btn btn-light">Reset</a>
      </div>
    </form>
  </div>
</div>
