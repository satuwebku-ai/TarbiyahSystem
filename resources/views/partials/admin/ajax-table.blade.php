{{-- Muat ulang tabel lewat AJAX tanpa memuat ulang halaman.
     Pasang di halaman:  <div data-ajax-region> @fragment('table') …tabel… @endfragment </div>
                         <form method="GET" data-ajax-filter> …filter… </form>
     dan di controller:  return view(...)->fragmentIf($request->ajax(), 'table');
     - Kirim form filter, klik pagination (.page-link), atau tautan [data-ajax] di dalam region → hanya region yang diganti.
     - Pilihan dropdown/tanggal di form filter langsung diterapkan (tanpa menekan tombol).
     - URL diperbarui (pushState), jadi bisa di-bookmark; tombol Kembali memuat ulang halaman.
     - Input tersembunyi [data-ajax-sync] ikut diperbarui dari tautan (mis. tab status).
     - Tautan di luar region dengan atribut data-ajax-qs ikut diperbarui query-nya (mis. Ekspor/Cetak).
     - Elemen form dengan data-ajax-full tidak memicu AJAX (tetap navigasi biasa).
     - Tambahkan data-ajax-nopush pada form bila URL tidak boleh berubah (tanpa ?search=...&type_id=...).
     - Tambahkan data-ajax-live pada input teks agar pencarian jalan otomatis saat mengetik (debounce).
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
  var pushUrl = !(form && form.hasAttribute('data-ajax-nopush'));

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
        syncLinks(url);
        if (scroll) region.scrollIntoView({ behavior: 'smooth', block: 'start' });
      })
      .catch(function () { window.location.href = url; });
  }

  // Tautan di luar region (mis. tombol Ekspor/Cetak) ikut membawa filter terbaru.
  function syncLinks(url) {
    var qs = new URL(url, location.href).search;
    document.querySelectorAll('a[data-ajax-qs]').forEach(function (a) { a.href = a.href.split('?')[0] + qs; });
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
      load(formUrl(), pushUrl, false);
    });
    // pencarian langsung saat mengetik
    form.addEventListener('input', function (e) {
      if (!e.target.matches('[data-ajax-live]')) return;
      clearTimeout(timer);
      timer = setTimeout(function () { load(formUrl(), pushUrl, false); }, 350);
    });
    // terapkan otomatis saat pilihan berubah (event 'change' dari tindakan pengguna, bukan sinkronisasi skrip)
    if (window.jQuery) {
      jQuery(form).on('change', 'select, input:not([type=hidden])', function () {
        if (this.hasAttribute('data-ajax-full')) return;   // dikirim sebagai navigasi biasa
        clearTimeout(timer);
        timer = setTimeout(function () { load(formUrl(), pushUrl, false); }, 250);
      });
    } else {
      form.addEventListener('change', function (e) {
        if (!e.target.matches('select, input:not([type=hidden])') || e.target.hasAttribute('data-ajax-full')) return;
        clearTimeout(timer);
        timer = setTimeout(function () { load(formUrl(), pushUrl, false); }, 250);
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
    load(a.href, pushUrl, a.matches('.page-link'));
  });

  window.addEventListener('popstate', function () { window.location.reload(); });
})();
</script>
@endonce
