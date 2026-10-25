{{-- Service worker portal pegawai. Hanya halaman offline dan ikon yang di-cache; halaman portal TIDAK pernah disimpan. --}}
const CACHE = 'pegawai-shell-{{ $version }}';
const OFFLINE_URL = @json($offlineUrl);
const PRECACHE = [OFFLINE_URL, ...@json($precache)];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('pegawai-shell-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // Hanya perpindahan halaman. Permintaan lain (CSS, JS, form, foto, API) dibiarkan ke jaringan seperti biasa.
  if (event.request.mode !== 'navigate') return;

  event.respondWith(
    fetch(event.request).catch(() => caches.match(OFFLINE_URL))
  );
});
