{{-- Jabatan mengikuti unit terpilih; keduanya Select2. Ganti unit → daftar jabatan dibangun ulang. --}}
<script>
(function () {
  var unit = document.getElementById('organizational_unit_id');
  var position = document.getElementById('organizational_position_id');
  if (!unit || !position || !window.jQuery || !jQuery.fn.select2) return;
  var $ = jQuery, $unit = $(unit), $pos = $(position);

  var all = Array.prototype.slice.call(position.options)
    .filter(function (o) { return o.value !== ''; })
    .map(function (o) { return { id: o.value, text: o.textContent, unit: o.dataset.unitId }; });
  var wanted = position.value; // nilai awal (old input / data yang sedang diubah)

  $pos.select2({ minimumResultsForSearch: 6, placeholder: 'Tanpa jabatan' });

  function rebuild(keep) {
    var current = keep ? wanted : '';
    $pos.empty().append(new Option('Tanpa jabatan', '', false, false));
    all.forEach(function (o) {
      if (unit.value !== '' && o.unit === unit.value) $pos.append(new Option(o.text, o.id, false, false));
    });
    if (!$pos.find('option').filter(function () { return this.value === current; }).length) current = '';
    $pos.val(current).trigger('change');
  }

  $unit.on('change', function () { wanted = ''; rebuild(false); });
  rebuild(true);
})();
</script>
