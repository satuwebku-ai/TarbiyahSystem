{{-- Tabel Yajra DataTables server-side.
  @include('partials.admin.datatable', [
     'id'      => 'tblUnitTypes',                       // id unik tabel
     'url'     => route('...index'),                    // endpoint data (controller: Dt::wants($request))
     'columns' => [ ['data'=>'name','title'=>'Nama'], ['data'=>'action','title'=>'Tindakan','orderable'=>false,'searchable'=>false,'className'=>'text-end text-nowrap'] ],
     'order'   => [[0,'asc']],                          // opsional
     'filter'  => '#filterForm',                        // opsional: form GET yang nilainya ikut dikirim
     'empty'   => 'Belum ada data.',                    // opsional
     'length'  => 20,                                   // opsional
  ])
  Kolom bernomor memakai data 'DT_RowIndex' (controller: ->addIndexColumn()). --}}
@include('partials.admin.datatable-assets')
@php
  $dtCols = collect($columns)->map(fn ($c) => array_merge(['orderable' => true, 'searchable' => true], $c))->values();
  $dtJsCols = $dtCols->map(function ($c) {
    return array_intersect_key($c, array_flip(['data', 'name', 'orderable', 'searchable', 'className']));
  })->all();
  $dtOrder = $order ?? [];
  $dtFilter = $filter ?? null;
  $dtEmpty = $empty ?? 'Belum ada data';
  $dtLength = (int) ($length ?? 20);
@endphp
<div class="table-responsive">
  <table id="{{ $id }}" class="table table-hover align-middle mb-0 w-100">
    <thead><tr>@foreach($dtCols as $c)<th class="{{ $c['headClass'] ?? '' }}">{{ $c['title'] ?? '' }}</th>@endforeach</tr></thead>
    <tbody></tbody>
  </table>
</div>
@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    window.TarbiyahDt('#{{ $id }}', {
      url: @json($url),
      columns: @json($dtJsCols),
      order: @json($dtOrder),
      filter: @json($dtFilter),
      empty: @json($dtEmpty),
      pageLength: {{ $dtLength }}
    });
  });
</script>
@endpush
