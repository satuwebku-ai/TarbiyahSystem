<script>
(function () {
  // Service worker: hanya halaman "tidak ada koneksi"; halaman portal tidak pernah disimpan.
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register({{ \Illuminate\Support\Js::from(route('pegawai.pwa.sw', [], false)) }}, { scope: {{ \Illuminate\Support\Js::from(rtrim(route('pegawai.dashboard', [], false), '/').'/') }} }).catch(function () {});
    });
  }

  // Ajakan memasang aplikasi. Hanya muncul di browser (bukan saat sudah terpasang) dan bisa ditutup.
  var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  var dismissedKey = 'TarbiyahSystem-pwa-dismissed';
  var dismissed = false;
  try { dismissed = localStorage.getItem(dismissedKey) === '1'; } catch (e) {}
  if (standalone || dismissed) return;

  var deferred = null;
  var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;

  function banner(text, actionLabel, onAction) {
    if (document.getElementById('pwaInstallBar')) return;
    var bar = document.createElement('div');
    bar.id = 'pwaInstallBar';
    bar.className = 'alert alert-primary d-flex align-items-center gap-2 flex-wrap py-2 mb-3';
    bar.setAttribute('role', 'region');

    var msg = document.createElement('span');
    msg.className = 'flex-grow-1 small';
    msg.textContent = text;
    bar.appendChild(msg);

    if (actionLabel) {
      var go = document.createElement('button');
      go.type = 'button'; go.className = 'btn btn-sm btn-primary'; go.textContent = actionLabel;
      go.addEventListener('click', onAction);
      bar.appendChild(go);
    }
    var close = document.createElement('button');
    close.type = 'button'; close.className = 'btn-close'; close.setAttribute('aria-label', 'Tutup');
    close.addEventListener('click', function () {
      bar.remove();
      try { localStorage.setItem(dismissedKey, '1'); } catch (e) {}
    });
    bar.appendChild(close);

    var main = document.querySelector('main') || document.body;
    main.insertBefore(bar, main.firstChild);
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    banner('Pasang aplikasi absensi di layar utama HP agar lebih cepat dibuka.', 'Pasang', function () {
      if (!deferred) return;
      deferred.prompt();
      deferred.userChoice.finally(function () {
        deferred = null;
        var bar = document.getElementById('pwaInstallBar');
        if (bar) bar.remove();
      });
    });
  });

  window.addEventListener('appinstalled', function () {
    var bar = document.getElementById('pwaInstallBar');
    if (bar) bar.remove();
  });

  // iOS Safari tidak punya tombol pasang otomatis.
  if (isIos) {
    window.addEventListener('load', function () {
      banner('Untuk memasang di iPhone/iPad: ketuk tombol Bagikan di Safari, lalu pilih "Tambahkan ke Layar Utama".', null, null);
    });
  }
})();
</script>
