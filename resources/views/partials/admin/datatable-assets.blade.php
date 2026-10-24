{{-- Aset Yajra DataTables (client) — cukup di-include sekali per halaman; memakai jQuery milik select2-assets. --}}
@include('partials.admin.select2-assets')
@once
@push('scripts')
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" crossorigin="anonymous">
<style>
  .dt-container { position: relative; padding: .75rem 1rem 0; }
  .dt-container .dt-search input,
  .dt-container .dt-length select { background-color: var(--bs-body-bg); color: var(--bs-body-color); border-color: var(--bs-border-color); border-radius: .5rem; }
  .dt-container .dt-info, .dt-container .dt-paging { padding-bottom: .75rem; font-size: .85rem; color: var(--bs-secondary-color); }
  .dt-container .dt-paging .page-link { border-radius: .5rem; margin: 0 .1rem; border-color: transparent; }
  [data-theme="dark"] .dt-container .dt-paging .page-link { background: transparent; }

  /* Tabel */
  .dt-container table.dataTable { border-collapse: separate; border-spacing: 0; }
  table.dataTable > thead > tr > th {
    white-space: nowrap; font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
    color: var(--bs-secondary-color); background: var(--bs-tertiary-bg);
    border-bottom: 1px solid var(--bs-border-color); padding: .7rem .85rem;
  }
  table.dataTable > thead > tr > th:first-child { border-top-left-radius: .6rem; }
  table.dataTable > thead > tr > th:last-child { border-top-right-radius: .6rem; }
  table.dataTable > thead .dt-orderable-asc, table.dataTable > thead .dt-orderable-desc { cursor: pointer; }
  table.dataTable > tbody > tr > td { padding: .75rem .85rem; vertical-align: middle; border-bottom: 1px solid var(--bs-border-color-translucent); }
  table.dataTable > tbody > tr:last-child > td { border-bottom: 0; }
  table.dataTable.table-hover > tbody > tr:hover > * { background-color: rgba(var(--accent-rgb, 99,102,241), .06); }

  /* Proses memuat: batang tipis di atas, isi tabel meredup, kotak "Memuat" ringkas */
  .dt-container.dt-loading::before {
    content: ""; position: absolute; left: 0; right: 0; top: 0; height: 3px; z-index: 6;
    background: linear-gradient(90deg, transparent, var(--bs-primary), transparent) no-repeat;
    background-size: 40% 100%; animation: dt-bar 1s linear infinite;
  }
  .dt-container.dt-loading table.dataTable > tbody { opacity: .5; transition: opacity .15s; }
  .dt-container .dt-processing {
    z-index: 5; width: auto; margin: 0; padding: .4rem .9rem; font-size: .85rem; transform: translate(-50%, -50%);
    background: var(--bs-body-bg); color: var(--bs-body-color); border: 1px solid var(--bs-border-color);
    border-radius: 999px; box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.12);
  }
  .dt-container .dt-processing > div { display: none; }
  .dt-container.dt-skeleton .dt-processing { display: none !important; }
  @keyframes dt-bar { from { background-position: -40% 0; } to { background-position: 140% 0; } }

  /* Kerangka (skeleton) saat pemuatan pertama */
  .dt-skel { display: block; height: .9rem; width: 70%; border-radius: .4rem;
    background: linear-gradient(90deg, var(--bs-tertiary-bg) 25%, var(--bs-border-color) 37%, var(--bs-tertiary-bg) 63%);
    background-size: 400% 100%; animation: dt-shimmer 1.2s ease infinite; }
  .dt-skel-row td:nth-child(even) .dt-skel { width: 45%; }
  @keyframes dt-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
  @media (prefers-reduced-motion: reduce) { .dt-skel, .dt-container.dt-loading::before { animation: none; } }

  /* Keadaan kosong & galat */
  .dt-empty, .dt-error { padding: 2rem 1rem; text-align: center; color: var(--bs-secondary-color); }
  .dt-empty-title { font-weight: 600; color: var(--bs-body-color); }
  .dt-error-title { font-weight: 600; color: var(--bs-danger); margin-bottom: .15rem; }

  @media (max-width: 576px) {
    .dt-container { padding: .5rem .5rem 0; }
    .dt-container .dt-layout-row { flex-direction: column; gap: .5rem; align-items: stretch; }
  }
</style>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js" crossorigin="anonymous"></script>
<script>
/**
 * window.TarbiyahDt(selector, { url, columns, order, filter, pageLength, drawCallback })
 *  - url     : endpoint JSON (route index yang sama; controller membalas Yajra bila ada parameter "draw")
 *  - columns : [{ data, title, orderable, searchable, className }]
 *  - filter  : selector <form> GET; nilainya ikut dikirim ke server, submit / perubahan → reload tanpa muat ulang halaman
 *  Alamat halaman dijaga bersih: filter tidak ditulis ke URL, dan query dari tautan masuk
 *  (mis. ?unit_id=20) dihapus dari address bar setelah halaman terbuka. keepUrl:true = perilaku lama.
 */
window.TarbiyahDt = function (selector, opts) {
  var $ = window.jQuery;
  if (!$ || !$.fn.DataTable) return null;
  // Galat ditampilkan di dalam tabel (bukan alert bawaan DataTables).
  $.fn.dataTable.ext.errMode = 'none';

  var $form = opts.filter ? $(opts.filter) : $();
  var $table = $(selector);
  var colCount = (opts.columns || []).length || 1;
  var timer = null;
  var firstLoad = true;

  function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

  function formParams() {
    var out = {};
    $form.serializeArray().forEach(function (f) { if (f.value !== '') out[f.name] = f.value; });
    return out;
  }
  function qsOf() { return $.param(formParams()); }
  var lastQs = qsOf();
  if (!opts.keepUrl && location.search && window.history && history.replaceState) {
    history.replaceState(history.state, '', location.pathname + location.hash);
  }

  function skeletonRows() {
    var rows = '';
    for (var r = 0; r < 6; r++) {
      rows += '<tr class="dt-skel-row">';
      for (var c = 0; c < colCount; c++) rows += '<td><span class="dt-skel"></span></td>';
      rows += '</tr>';
    }
    return rows;
  }

  function errorMessage(xhr, textStatus) {
    var json = xhr && xhr.responseJSON;
    if (textStatus === 'timeout') return 'Server terlalu lama menjawab. Coba lagi sebentar lagi.';
    if (textStatus === 'parsererror') return 'Respons server tidak valid (sesi mungkin berakhir). Muat ulang halaman.';
    if (!xhr || xhr.status === 0) return 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
    if (xhr.status === 403) return 'Anda tidak memiliki akses ke data ini.';
    if (xhr.status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
    if (json && json.error) return json.error;
    return 'Terjadi kesalahan di server (kode ' + xhr.status + '). Hubungi admin bila berulang.';
  }

  function showError(xhr, textStatus) {
    var $box = $('<div class="dt-error" role="alert"></div>');
    $('<div class="dt-error-title"></div>').text('Data gagal dimuat').appendTo($box);
    $('<div></div>').text(errorMessage(xhr, textStatus)).appendTo($box);
    $('<button type="button" class="btn btn-sm btn-outline-primary mt-2"></button>').text('Coba lagi').on('click', function () {
      lastQs = qsOf();
      $table.DataTable().ajax.reload(null, false);
    }).appendTo($box);
    $table.children('tbody').empty().append(
      $('<tr class="dt-error-row"></tr>').append($('<td></td>').attr('colspan', colCount).append($box))
    );
    $table.closest('.dt-container').find('.dt-info').text('');
  }

  function wrap() { return $table.closest('.dt-container'); }

  // Dipasang SEBELUM DataTable() agar permintaan pertama ikut tertangkap.
  $table.on('preXhr.dt', function () {
    if (firstLoad) {
      wrap().addClass('dt-skeleton');
      $table.children('tbody').html(skeletonRows());
    }
  });
  $table.on('xhr.dt', function () { firstLoad = false; wrap().removeClass('dt-skeleton'); });
  $table.on('processing.dt', function (e, settings, processing) {
    $(settings.nTableWrapper).toggleClass('dt-loading', !!processing).attr('aria-busy', processing ? 'true' : 'false');
  });

  var table = $table.DataTable({
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
      timeout: 30000,
      data: function (d) { $.extend(d, formParams()); },
      // Penanganan ini menggantikan bawaan DataTables, jadi indikator "Memuat" harus dimatikan sendiri.
      error: function (xhr, textStatus) {
        if (xhr && (xhr.status === 401 || xhr.status === 419)) { window.location.reload(); return; }
        if (textStatus === 'abort') return;
        firstLoad = false;
        wrap().removeClass('dt-skeleton');
        $table.DataTable().processing(false);
        showError(xhr, textStatus);
      }
    },
    language: {
      processing: '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Memuat…',
      search: 'Cari:',
      lengthMenu: 'Tampilkan _MENU_ data',
      info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
      infoEmpty: 'Tidak ada data',
      infoFiltered: '(disaring dari _MAX_ data)',
      zeroRecords: '<div class="dt-empty"><div class="dt-empty-title">Tidak ada data yang cocok</div><div>Ubah kata kunci atau filter pencarian.</div></div>',
      emptyTable: '<div class="dt-empty"><div class="dt-empty-title">' + esc(opts.empty || 'Belum ada data') + '</div></div>',
      loadingRecords: 'Memuat…',
      paginate: { first: '«', last: '»', next: '›', previous: '‹' }
    },
    drawCallback: function () {
      if (typeof opts.drawCallback === 'function') opts.drawCallback.apply(this, arguments);
    }
  });

  // force=true: tindakan eksplisit (submit, reset, tab) selalu memuat ulang; selain itu lewati bila filter tidak berubah.
  function reload(push, force) {
    var qs = qsOf();
    if (!force && qs === lastQs) return;
    lastQs = qs;
    // Filter sengaja TIDAK ditulis ke alamat halaman (opts.keepUrl: true untuk mengaktifkannya kembali).
    if (push && opts.keepUrl && window.history && history.replaceState) {
      history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
    }
    table.ajax.reload(null, true);
  }

  if ($form.length) {
    $form.on('submit', function (e) { e.preventDefault(); clearTimeout(timer); reload(true, true); });
    // Pilihan (select/checkbox/tanggal) langsung memuat ulang; kolom teks mencari otomatis saat berhenti mengetik.
    $form.on('change', 'select, input:not([type=hidden]):not([type=text]):not([type=search])', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { reload(true); }, 250);
    });
    $form.on('input', 'input[type=text], input[type=search]', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { reload(true); }, 400);
    });
    // tombol/tautan Reset: kosongkan filter lalu muat ulang tabel
    $form.on('click', '[data-dt-reset]', function (e) {
      e.preventDefault();
      $form.find('select').val('').trigger('change.select2');
      $form.find('input:not([type=hidden])').val('');
      clearTimeout(timer); reload(true, true);
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
      clearTimeout(timer); reload(true, true);
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
