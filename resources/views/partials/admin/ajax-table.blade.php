{{-- Muat ulang tabel lewat AJAX tanpa memuat ulang halaman.
     Pasang di halaman:  <div data-ajax-region> @fragment('table') …tabel… @endfragment </div>
                         <form method="GET" data-ajax-filter> …filter… </form>
     dan di controller:  return view(...)->fragmentIf($request->ajax(), 'table');
     - Kirim form filter, klik pagination (.page-link), atau tautan [data-ajax] di dalam region → hanya region yang diganti.
     - Pilihan dropdown/tanggal di form filter langsung diterapkan (tanpa menekan tombol).
     - URL diperbarui (pushState), jadi bisa di-bookmark; tombol Kembali memuat ulang halaman.
     - Input tersembunyi [data-ajax-sync] ikut diperbarui dari tautan (mis. tab status).
     - Bila gagal / sesi habis (redirect), otomatis jatuh ke navigasi biasa.
     Butuh @include('partials.admin.select2-assets') bila ingin pilihan otomatis diterapkan (memakai jQuery). --}}
@once
<style>
  [data-ajax-region] { transition: opacity .15s; }
  [data-ajax-region].is-loading { opacity: .45; pointer-events: none; }
</style>
<script>
(function () {
  var region = document.querySelector('[data-ajax-region]');
  if (!region || !window.fetch) return;
  var form = document.querySelector('form[data-ajax-filter]');
  var seq = 0, timer = null;

  function load(url, push, scroll) {
    var n = ++seq;
    region.classList.add('is-loading');
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
      .then(function (r) {
        if (!r.ok || r.redirected) throw new Error('status ' + r.status);
        return r.text();
      })
      .then(function (html) {
        if (n !== seq) return;           // permintaan lebih baru sudah berjalan
        region.innerHTML = html;
        region.classList.remove('is-loading');
        if (push) history.pushState({ ajax: 1 }, '', url);
        if (scroll) region.scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .catch(function () { window.location.href = url; });
  }

  function formUrl() {
    var params = new URLSearchParams();
    new FormData(form).forEach(function (v, k) { if (v !== '') params.append(k, v); });
    var qs = params.toString();
    return form.getAttribute('action') + (qs ? '?' + qs : '');
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearTimeout(timer);
      load(formUrl(), true, false);
    });
    // terapkan otomatis saat pilihan berubah (event 'change' dari tindakan pengguna, bukan sinkronisasi skrip)
    if (window.jQuery) {
      jQuery(form).on('change', 'select, input:not([type=hidden])', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { load(formUrl(), true, false); }, 250);
      });
    }
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a');
    if (!a || !region.contains(a) || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
    if (!a.matches('.page-link, [data-ajax]') || !a.href) return;
    e.preventDefault();
    if (form) {
      var q = new URL(a.href, location.href).searchParams;
      form.querySelectorAll('input[data-ajax-sync]').forEach(function (i) { if (q.has(i.name)) i.value = q.get(i.name); });
    }
    load(a.href, true, a.matches('.page-link'));
  });

  window.addEventListener('popstate', function () { window.location.reload(); });
})();
</script>
@endonce
