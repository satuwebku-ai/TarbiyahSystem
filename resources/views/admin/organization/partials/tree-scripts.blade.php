<script>
(function () {
  var tree = document.getElementById('orgTree');
  if (!tree) return;
  var $ = function (id) { return document.getElementById(id); };

  function setAll(open) {
    tree.querySelectorAll('.org-tree-children').forEach(function (el) {
      el.classList.toggle('show', open);
    });
    tree.querySelectorAll('button.org-tree-toggle').forEach(function (b) {
      b.classList.toggle('collapsed', !open);
    });
    tree.querySelectorAll('[aria-expanded]').forEach(function (li) {
      li.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  if ($('orgExpandAll')) $('orgExpandAll').addEventListener('click', function () { setAll(true); });
  if ($('orgCollapseAll')) $('orgCollapseAll').addEventListener('click', function () { setAll(false); });

  // sinkronkan aria-expanded saat collapse berubah
  tree.addEventListener('shown.bs.collapse', function (e) { e.target.parentElement.setAttribute('aria-expanded', 'true'); });
  tree.addEventListener('hidden.bs.collapse', function (e) { e.target.parentElement.setAttribute('aria-expanded', 'false'); });

  // pencarian: tampilkan unit yang cocok beserta induknya
  var input = $('orgSearch'), empty = $('orgTreeEmpty');
  if (!input) return;
  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var items = tree.querySelectorAll('.org-tree-item');
    if (!q) {
      items.forEach(function (li) { li.classList.remove('org-hide'); });
      empty.classList.add('d-none');
      return;
    }
    items.forEach(function (li) { li.classList.add('org-hide'); });
    var found = 0;
    items.forEach(function (li) {
      var row = li.querySelector(':scope > .org-tree-row');
      if (row.textContent.toLowerCase().indexOf(q) === -1) return;
      found++;
      for (var n = li; n && n !== tree; n = n.parentElement) {
        if (n.classList.contains('org-tree-item')) n.classList.remove('org-hide');
      }
    });
    if (found) setAll(true);
    empty.classList.toggle('d-none', found > 0);
  });
})();
</script>
