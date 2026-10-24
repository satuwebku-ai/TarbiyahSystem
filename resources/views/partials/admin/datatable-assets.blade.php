{{-- Aset Yajra DataTables (client) — cukup di-include sekali per halaman; memakai jQuery milik select2-assets. --}}
@include('partials.admin.select2-assets')
@once
@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" crossorigin="anonymous">
<style>
  .dt-container { padding: .75rem 1rem 0; }
  .dt-container .dt-search input,
  .dt-container .dt-length select { background-color: var(--bs-body-bg); color: var(--bs-body-color); border-color: var(--bs-border-color); }
  .dt-container .dt-info, .dt-container .dt-paging { padding-bottom: .75rem; font-size: .85rem; }
  .dt-container .dt-processing { z-index: 5; }
  table.dataTable > thead > tr > th { white-space: nowrap; }
  table.dataTable > thead .dt-orderable-asc, table.dataTable > thead .dt-orderable-desc { cursor: pointer; }
  table.dataTable.table-hover > tbody > tr:hover > * { background-color: rgba(var(--accent-rgb, 99,102,241), .06); }
  [data-theme="dark"] .dt-container .dt-paging .page-link { background: transparent; }
</style>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js" crossorigin="anonymous"></script>
<script>
/**
 * window.TarbiyahDt(selector, { url, columns, order, filter, pageLength, drawCallback })
 *  - url     : endpoint JSON (route index yang sama; controller membalas Yajra bila ada parameter "draw")
 *  - columns : [{ data, title, orderable, searchable, className }]
 *  - filter  : selector <form> GET; nilainya ikut dikirim ke server, submit / perubahan → reload tanpa muat ulang halaman
 *  URL halaman ikut diperbarui (replaceState) supaya filter tetap bisa di-bookmark.
 */
window.TarbiyahDt = function (selector, opts) {
  var $ = window.jQuery;
  if (!$ || !$.fn.DataTable) return null;
  var $form = opts.filter ? $(opts.filter) : $();
  var timer = null;

  function formParams() {
    var out = {};
    $form.serializeArray().forEach(function (f) { if (f.value !== '') out[f.name] = f.value; });
    return out;
  }

  var table = $(selector).DataTable({
    processing: true,
    serverSide: true,
    searchDelay: 400,
    pageLength: opts.pageLength || 20,
    lengthMenu: [10, 20, 25, 50, 100],
    order: opts.order || [],
    columns: opts.columns,
    layout: opts.layout || undefined,
    ajax: {
      url: opts.url,
      type: 'GET',
      data: function (d) { $.extend(d, formParams()); },
      error: function (xhr) {
        if (xhr.status === 401 || xhr.status === 419) { window.location.reload(); }
      }
    },
    language: {
      processing: 'Memuat…',
      search: 'Cari:',
      lengthMenu: 'Tampilkan _MENU_ data',
      info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
      infoEmpty: 'Tidak ada data',
      infoFiltered: '(disaring dari _MAX_ data)',
      zeroRecords: 'Tidak ada data yang cocok',
      emptyTable: opts.empty || 'Belum ada data',
      loadingRecords: 'Memuat…',
      paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    },
    drawCallback: function () {
      if (typeof opts.drawCallback === 'function') opts.drawCallback.apply(this, arguments);
    }
  });

  function reload(push) {
    if (push && window.history && history.replaceState) {
      var qs = $.param(formParams());
      history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    }
    table.ajax.reload(null, true);
  }

  if ($form.length) {
    $form.on('submit', function (e) { e.preventDefault(); clearTimeout(timer); reload(true); });
    $form.on('change', 'select, input:not([type=hidden])', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { reload(true); }, 250);
    });
    // tombol/tautan Reset: kosongkan filter lalu muat ulang tabel
    $form.on('click', '[data-dt-reset]', function (e) {
      e.preventDefault();
      $form.find('select').val('').trigger('change.select2');
      $form.find('input:not([type=hidden])').val('');
      clearTimeout(timer); reload(true);
    });
  }

  // Tab status (mis. nav-pills): <a data-dt-tab="status" data-value="pending"> mengisi input hidden name="status" di form filter
  if ($form.length) {
    $(document).on('click', '[data-dt-tab]', function (e) {
      e.preventDefault();
      var $a = $(this);
      $form.find('input[name="' + $a.data('dt-tab') + '"]').val($a.data('value'));
      $a.closest('ul').find('[data-dt-tab]').removeClass('active');
      $a.addClass('active');
      clearTimeout(timer); reload(true);
    });
  }

  // Konfirmasi hapus untuk form di dalam tabel (kolom dirender ulang oleh DataTables, jadi delegasi)
  $(selector).on('submit', 'form[data-confirm]', function (e) {
    if (!window.confirm(this.getAttribute('data-confirm'))) e.preventDefault();
  });
  return table;
};
</script>
@endpush
@endonce
