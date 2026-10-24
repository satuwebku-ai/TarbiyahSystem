<script>
(function () {
  // Semua handler memakai delegasi + pencarian elemen saat dipakai, supaya tetap jalan
  // setelah bagan diganti lewat AJAX (data-ajax-region).
  function tree() { return document.getElementById('orgTree'); }

  function setAll(open) {
    var t = tree(); if (!t) return;
    t.querySelectorAll('.org-tree-children').forEach(function (el) { el.classList.toggle('show', open); });
    t.querySelectorAll('button.org-tree-toggle').forEach(function (b) { b.classList.toggle('collapsed', !open); });
    t.querySelectorAll('[aria-expanded]').forEach(function (li) { li.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('#orgExpandAll')) setAll(true);
    else if (e.target.closest('#orgCollapseAll')) setAll(false);
  });

  // sinkronkan aria-expanded saat collapse berubah
  document.addEventListener('shown.bs.collapse', function (e) {
    if (e.target.closest('#orgTree')) e.target.parentElement.setAttribute('aria-expanded', 'true');
  });
  document.addEventListener('hidden.bs.collapse', function (e) {
    if (e.target.closest('#orgTree')) e.target.parentElement.setAttribute('aria-expanded', 'false');
  });

  // pencarian sisi-klien (hanya dasbor organisasi yang memiliki #orgSearch)
  document.addEventListener('input', function (e) {
    if (e.target.id !== 'orgSearch') return;
    var t = tree(), empty = document.getElementById('orgTreeEmpty');
    if (!t) return;
    var q = e.target.value.trim().toLowerCase();
    var items = t.querySelectorAll('.org-tree-item');
    if (!q) {
      items.forEach(function (li) { li.classList.remove('org-hide'); });
      if (empty) empty.classList.add('d-none');
      return;
    }
    items.forEach(function (li) { li.classList.add('org-hide'); });
    var found = 0;
    items.forEach(function (li) {
      var row = li.querySelector(':scope > .org-tree-row');
      if (row.textContent.toLowerCase().indexOf(q) === -1) return;
      found++;
      for (var n = li; n && n !== t; n = n.parentElement) {
        if (n.classList.contains('org-tree-item')) n.classList.remove('org-hide');
      }
    });
    if (found) setAll(true);
    if (empty) empty.classList.toggle('d-none', found > 0);
  });
})();
</script>
