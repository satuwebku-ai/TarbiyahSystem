{{-- KOMWIL → KOMDA → Sekolah → (Tahun ajaran) → Kelas / Pegawai saling terhubung, semuanya Select2 yang bisa dicari.
     Elemen dikenali lewat id: #komwil_id #komda_id #school_id #academic_year_id #classroom_id #employee_id (semua opsional).
     Opsi membawa data-komwil-id / data-komda-id / data-school-id / data-year-id.
     Nilainya boleh berisi beberapa id dipisah koma (pegawai yang ditempatkan di beberapa sekolah).
     - Mengganti tingkat atas menyaring tingkat di bawahnya (nilai yang masih cocok dipertahankan).
     - Memilih tingkat bawah (mis. kelas/sekolah) mengisi otomatis tingkat atasnya (bila hanya satu kemungkinan).
     - Pegawai bersifat "ketat": bila sekolah/KOMDA/KOMWIL dipilih, hanya pegawai di sana yang muncul.
     - Select2 tetap dipasang walau hanya satu tingkat yang tampil (mis. KOMDA hanya melihat dropdown Sekolah).
     Butuh @include('partials.admin.select2-assets') lebih dulu. --}}
<script>
(function () {
  if (!window.jQuery || !jQuery.fn.select2) return;
  var $ = jQuery;
  var spec = [['komwil', 'komwil_id'], ['komda', 'komda_id'], ['school', 'school_id'], ['year', 'academic_year_id'], ['classroom', 'classroom_id'], ['employee', 'employee_id']];
  var ATTRS = ['komwil', 'komda', 'school', 'year'];
  var STRICT = { employee: true };   // tanpa atribut yang cocok = tersembunyi saat tingkat atas dipilih
  var NO_UPSTREAM = { employee: true }; // pegawai bisa di banyak sekolah: jangan menebak tingkat atasnya
  var levels = [];
  spec.forEach(function (s) {
    var el = document.getElementById(s[1]);
    if (el) levels.push({ key: s[0], el: el, select: el.tagName === 'SELECT' });
  });

  function ids(v) { return v ? String(v).split(',').filter(Boolean) : []; }

  levels.forEach(function (l) {
    if (!l.select) return;
    var first = l.el.options[0];
    l.placeholder = first && first.value === '' ? first.textContent.trim() : 'Pilih…';
    l.all = Array.prototype.slice.call(l.el.options).filter(function (o) { return o.value !== ''; }).map(function (o) {
      var d = o.dataset;
      return { value: o.value, text: o.textContent.trim(),
               attrs: { komwil: ids(d.komwilId), komda: ids(d.komdaId), school: ids(d.schoolId), year: ids(d.yearId) } };
    });
  });

  // Select2 selalu dipasang, juga bila hanya ada satu tingkat.
  levels.forEach(function (l) {
    if (l.select) $(l.el).select2({ minimumResultsForSearch: 0, placeholder: l.placeholder });
  });
  if (levels.length < 2) return;

  function byKey(k) { return levels.filter(function (l) { return l.key === k; })[0]; }

  // Tahun ajaran & kelas hanya membawa data-school-id: turunkan KOMDA/KOMWIL dari sekolahnya.
  var schoolMap = {};
  var sl = byKey('school');
  if (sl && sl.select) sl.all.forEach(function (o) { schoolMap[o.value] = o.attrs; });
  levels.forEach(function (l) {
    if (!l.select || l.key === 'school' || l.key === 'employee') return;
    l.all.forEach(function (o) {
      var m = o.attrs.school.length === 1 && schoolMap[o.attrs.school[0]];
      if (m) {
        if (!o.attrs.komda.length) o.attrs.komda = m.komda;
        if (!o.attrs.komwil.length) o.attrs.komwil = m.komwil;
      }
    });
  });

  function selection() {
    var s = {};
    levels.forEach(function (l) { s[l.key] = l.el.value; });
    return s;
  }
  function matches(o, l, sel) {
    return ATTRS.every(function (k) {
      if (k === l.key || !sel[k]) return true;
      if (!o.attrs[k].length) return !STRICT[l.key];
      return o.attrs[k].indexOf(sel[k]) > -1;
    });
  }
  function setValue(l, v) { $(l.el).val(v).trigger('change.select2'); }

  function refill(l) {
    var current = l.el.value, sel = selection();
    var items = l.all.filter(function (o) { return matches(o, l, sel); });
    var $el = $(l.el);
    $el.empty().append(new Option(l.placeholder, '', false, false));
    items.forEach(function (o) { $el.append(new Option(o.text, o.value, false, false)); });
    var ok = items.some(function (o) { return o.value === current; });
    $el.val(ok ? current : '').trigger('change.select2');
  }

  // pilihan di tingkat bawah → isi tingkat atasnya (hanya bila tepat satu kemungkinan)
  function fillUpstream(l) {
    if (NO_UPSTREAM[l.key]) return;
    var opt = l.select && l.all.filter(function (o) { return o.value === l.el.value; })[0];
    if (!opt) return;
    var idx = levels.indexOf(l);
    levels.slice(0, idx).forEach(function (u) {
      var want = opt.attrs[u.key];
      if (u.select && want && want.length === 1 && u.el.value !== want[0]) setValue(u, want[0]);
    });
  }

  var busy = false;
  function refresh(changed) {
    if (busy) return;
    busy = true;
    if (changed) fillUpstream(changed);
    levels.forEach(function (l) { if (l.select && l !== changed) refill(l); });
    busy = false;
  }

  levels.forEach(function (l) {
    if (l.select) $(l.el).on('change', function () { refresh(l); });
  });

  // keadaan awal: ikuti pilihan terdalam yang sudah terisi (mis. kelas dari URL)
  var deepest = null;
  levels.forEach(function (l) { if (l.select && l.el.value) deepest = l; });
  busy = true;
  if (deepest) fillUpstream(deepest);
  levels.forEach(function (l) { if (l.select) refill(l); });
  busy = false;
})();
</script>
