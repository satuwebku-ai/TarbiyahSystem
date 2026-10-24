{{-- Filter rekap: periode + KOMWIL → KOMDA → Sekolah (Select2, bisa dicari, saling menyaring). --}}
@include('partials.admin.select2-assets')
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <form method="GET" action="{{ $action }}" class="row g-2 align-items-end" data-ajax-filter data-ajax-nopush>
      <div class="col-md-2">
        <label class="form-label small mb-1" for="period">{{ $periodLabel }}</label>
        <input id="period" type="{{ $periodType }}" name="{{ $periodName }}" value="{{ $periodValue }}" class="form-control">
      </div>
      @include('partials.admin.school-filter', [
        'scope' => $scope,
        'selected' => ['komwil' => $filters['komwil'] ?? '', 'komda' => $filters['komda'] ?? '', 'school' => $filters['school'] ?? ''],
        'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-4'],
        'allLabel' => 'Semua sekolah / unit dalam cakupan',
      ])
      <div class="col-auto d-flex gap-2"><button class="btn btn-primary" type="submit">Tampilkan</button><a href="{{ $action }}" class="btn btn-light">Reset</a></div>
    </form>
  </div>
</div>
@push('scripts')
  @include('partials.admin.school-classroom-sync')
    @include('partials.admin.ajax-table')
@endpush
