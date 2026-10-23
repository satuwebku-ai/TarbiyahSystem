{{-- Select2 + tema Bootstrap 5. Cukup di-include sekali per halaman (di awal konten, sebelum @push script halaman). --}}
@once
@push('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" crossorigin="anonymous">
<style>
  .select2-container { max-width:100%; }
  .select2-container--bootstrap-5 .select2-selection { min-height:calc(1.5em + .75rem + 2px); }
  .select2-container--bootstrap-5 .select2-dropdown { z-index:2050; box-shadow:0 10px 30px rgba(15,23,42,.15); }
  .select2-container--bootstrap-5 .select2-results__options { max-height:320px; }
  .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field { border-radius:.4rem; }
  .s2-unit { line-height:1.25; padding-block:.1rem; }
  .s2-unit.s2-child { border-left:2px solid rgba(128,128,128,.35); margin-left:.15rem; }
  .s2-unit-name { font-weight:600; }
  .s2-unit-meta { font-size:.78rem; opacity:.7; white-space:normal; }
  .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice { margin:.15rem .25rem .15rem 0; }
  .s2-sel-code { opacity:.65; margin-left:.35rem; font-size:.85em; }
  .select2-results__option--highlighted .s2-unit-meta { opacity:.85; }
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/id.js" crossorigin="anonymous"></script>
<script>
(function () {
  if (!window.jQuery || !jQuery.fn.select2) return;
  var $ = jQuery;
  $.fn.select2.defaults.set('theme', 'bootstrap-5');
  $.fn.select2.defaults.set('language', 'id');
  $.fn.select2.defaults.set('width', '100%');

  // pencarian unit: cocok ke nama, kode, maupun rantai induknya
  function unitMatcher(params, data) {
    var term = $.trim(params.term || '').toLowerCase();
    if (term === '') return data;
    var el = data.element || {}, ds = el.dataset || {};
    var hay = [data.text, ds.code || '', ds.path || ''].join(' ').toLowerCase();
    return hay.indexOf(term) > -1 ? data : null;
  }
  function unitResult(state) {
    if (!state.id) return state.text;
    var ds = state.element.dataset, depth = parseInt(ds.depth || '0', 10);
    var $row = $('<div class="s2-unit"></div>');
    if (depth > 0) $row.addClass('s2-child').css('margin-left', (depth * 14) + 'px').css('padding-left', '.5rem');
    $row.append($('<div class="s2-unit-name"></div>').text(ds.name || state.text));
    $row.append($('<div class="s2-unit-meta"></div>').text((ds.code || '') + (ds.path ? ' · ' + ds.path : '')));
    return $row;
  }
  function unitSelection(state) {
    if (!state.id || !state.element) return state.text;
    var ds = state.element.dataset;
    return $('<span></span>').text(ds.name || state.text)
      .append($('<span class="s2-sel-code"></span>').text(ds.code ? '· ' + ds.code : ''));
  }

  $('select.js-unit-select').each(function () {
    $(this).select2({
      placeholder: $(this).data('placeholder') || 'Cari atau pilih unit…',
      matcher: unitMatcher,
      templateResult: unitResult,
      templateSelection: unitSelection,
      minimumResultsForSearch: 0
    });
  });
  // pilihan banyak unit (mis. cakupan unit akun pengelola)
  $('select.js-unit-multi').each(function () {
    $(this).select2({
      placeholder: $(this).data('placeholder') || 'Cari atau pilih unit…',
      matcher: unitMatcher,
      templateResult: unitResult,
      templateSelection: function (state) { return state.element ? (state.element.dataset.name || state.text) : state.text; },
      closeOnSelect: false,
      allowClear: true
    });
  });
  // select biasa yang ingin dicari: tambahkan class js-select2
  $('select.js-select2').each(function () { $(this).select2({ minimumResultsForSearch: 0 }); });
  // fokuskan kolom cari otomatis saat dropdown dibuka
  $(document).on('select2:open', function () {
    var f = document.querySelector('.select2-container--open .select2-search__field');
    if (f) f.focus();
  });
})();
</script>
@endpush
@endonce
