/*!
 * theme-color.js — Pengaturan warna template TarbiyahSystem Admin.
 *
 * Satu warna dasar -> seluruh palet (primary, aksen, sidebar/topbar, hero, tint)
 * dibuat otomatis lalu ditulis sebagai CSS variable di <html>. Karena CSS
 * dashboard memakai variabel yang sama untuk tema terang DAN gelap, warna
 * yang dipilih langsung berlaku di keduanya. Pilihan disimpan di localStorage
 * ('TarbiyahSystem-color') dan diterapkan lagi sebelum halaman tampil (tanpa kedip).
 *
 * Dimuat DUA kali secara efektif: sekali di awal <body> (terapkan warna
 * tersimpan), UI panel baru dipasang setelah DOM siap.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'TarbiyahSystem-color';
  var THEME_KEY = 'TarbiyahSystem-theme';
  var HARMONY_KEY = 'TarbiyahSystem-color-harmony';   // '0' = hanya warna utama yang berganti
  var DEFAULT_HEX = '#4f46e5';

  // Semua preset sudah lolos kontras teks putih (>= 4.5:1).
  var PRESETS = [
    { name: 'Indigo', hex: '#4f46e5' },
    { name: 'Biru',   hex: '#2563eb' },
    { name: 'Cyan',   hex: '#0e7490' },
    { name: 'Teal',   hex: '#0f766e' },
    { name: 'Hijau',  hex: '#047857' },
    { name: 'Oranye', hex: '#c2410c' },
    { name: 'Merah',  hex: '#dc2626' },
    { name: 'Rose',   hex: '#e11d48' },
    { name: 'Ungu',   hex: '#7c3aed' },
    { name: 'Slate',  hex: '#475569' }
  ];

  // Warna "bawaan" desain (hex asli di dashboard.css) per kelompok. Tiap hex punya variabel --k-<hex>
  // yang dipakai CSS dengan fallback ke hex asli, jadi tanpa override tampilan = desain awal.
  var GROUPS = {
      "success": [
          "10b981",
          "059669",
          "34d399",
          "6ee7b7",
          "047857",
          "065f46",
          "d1fae5",
          "ecfdf5",
          "f0fdf4",
          "bbf7d0",
          "132b26",
          "f0fdfa"
      ],
      "warning": [
          "f59e0b",
          "fbbf24",
          "d97706",
          "b45309",
          "fde68a",
          "fef3c7",
          "fffbeb",
          "92400e",
          "2b2412",
          "fefce8"
      ],
      "danger": [
          "f43f5e",
          "fb7185",
          "e11d48",
          "be123c",
          "fda4af",
          "fecdd3",
          "ffe4e6",
          "fff1f2",
          "9f1239",
          "2b131c",
          "ef4444",
          "fecaca",
          "fff5f5",
          "fef2f2"
      ],
      "info": [
          "0ea5e9",
          "0284c7"
      ],
      "purple": [
          "8b5cf6",
          "7c3aed",
          "ddd6fe",
          "9333ea",
          "f3e8ff",
          "f5f3ff",
          "1a1230"
      ],
      "pink": [
          "ec4899",
          "db2777",
          "fdf2f8",
          "fbcfe8",
          "fdf4ff",
          "1c1420"
      ],
      "cyan": [
          "06b6d4",
          "0891b2"
      ],
      "teal": [
          "14b8a6",
          "0f766e"
      ],
      "orange": [
          "f97316",
          "ea580c",
          "fff7ed"
      ],
      "lime": [
          "84cc16",
          "65a30d"
      ],
      "neutral": [
          "2d3a52",
          "e2e8f0",
          "1a2234",
          "94a3b8",
          "475569",
          "0f172a",
          "64748b",
          "cbd5e1",
          "334155",
          "f1f5f9",
          "0f1626",
          "8b96ab",
          "28344a",
          "222c42",
          "f8fafc",
          "0b1120",
          "1e293b",
          "141b2c",
          "152033"
      ]
  };

  var VARS = [
    '--c-primary', '--c-primary-rgb', '--c-primary-hover',
    '--accent', '--accent-rgb', '--accent-soft', '--accent-soft-rgb', '--accent-glow',
    '--c-deep-1', '--c-deep-2', '--c-deep-3',
    '--c-vio-1', '--c-vio-2', '--c-vio-3', '--c-vio-rgb', '--c-end',
    '--c-tint-50', '--c-tint-100', '--c-tint-200'
  ];
  var K_VARS = [];
  Object.keys(GROUPS).forEach(function (g) {
    GROUPS[g].forEach(function (h) { K_VARS.push('--k-' + h, '--k-' + h + '-rgb'); });
  });

  /* ───────── util warna ───────── */
  function clamp(v, a, b) { return Math.min(b, Math.max(a, v)); }

  function normHex(v) {
    if (typeof v !== 'string') return null;
    v = v.trim().replace(/^#/, '');
    if (/^[0-9a-f]{3}$/i.test(v)) v = v.replace(/./g, function (c) { return c + c; });
    return /^[0-9a-f]{6}$/i.test(v) ? '#' + v.toLowerCase() : null;
  }

  function hexToRgb(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
  }

  function rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(function (x) {
      var h = Math.round(clamp(x, 0, 255)).toString(16);
      return h.length < 2 ? '0' + h : h;
    }).join('');
  }

  function hexToHsl(hex) {
    var c = hexToRgb(hex), r = c[0] / 255, g = c[1] / 255, b = c[2] / 255;
    var max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2, h = 0, s = 0, d = max - min;
    if (d) {
      s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
      if (max === r) h = ((g - b) / d + (g < b ? 6 : 0));
      else if (max === g) h = (b - r) / d + 2;
      else h = (r - g) / d + 4;
      h *= 60;
    }
    return { h: h, s: s * 100, l: l * 100 };
  }

  function hslToHex(h, s, l) {
    h = ((h % 360) + 360) % 360; s = clamp(s, 0, 100) / 100; l = clamp(l, 0, 100) / 100;
    var k = function (n) { return (n + h / 30) % 12; };
    var a = s * Math.min(l, 1 - l);
    var f = function (n) { return l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1))); };
    return rgbToHex(f(0) * 255, f(8) * 255, f(4) * 255);
  }

  function luminance(hex) {
    var c = hexToRgb(hex).map(function (v) {
      v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }

  function contrastWhite(hex) { return 1.05 / (luminance(hex) + 0.05); }

  /* Pastikan warna primary cukup gelap untuk teks putih (tombol, badge). */
  function ensureReadable(hex) {
    var hsl = hexToHsl(hex), out = hex, guard = 0;
    while (contrastWhite(out) < 4.5 && hsl.l > 8 && guard++ < 40) {
      hsl.l -= 1.5; out = hslToHex(hsl.h, hsl.s, hsl.l);
    }
    return out;
  }

  /* ───────── pembuat palet ───────── */
  function buildPalette(inputHex) {
    var primary = ensureReadable(normHex(inputHex) || DEFAULT_HEX);
    var p = hexToHsl(primary);
    var h = p.h, s = clamp(p.s, 8, 92), h2 = h + 20;   // h2 = hue pendamping (ungu pada indigo)

    var accent = hslToHex(h, s, clamp(p.l + 8, 46, 66));
    var soft = hslToHex(h, Math.min(s, 85), clamp(p.l + 16, 66, 76));
    var glow = hslToHex(h, Math.min(s, 85), 82);
    var pal = {
      '--c-primary': primary,
      '--c-primary-hover': hslToHex(h, s, p.l - 7),
      '--accent': accent,
      '--accent-soft': soft,
      '--accent-glow': glow,
      '--c-deep-1': hslToHex(h, clamp(s * 0.6, 8, 60), 18),
      '--c-deep-2': hslToHex(h, clamp(s * 0.6, 8, 60), 29),
      '--c-deep-3': hslToHex(h2, clamp(s * 0.85, 8, 75), 30),
      '--c-vio-1': hslToHex(h2, clamp(s, 10, 85), 40),
      '--c-vio-2': hslToHex(h2, clamp(s, 10, 90), 48),
      '--c-vio-3': hslToHex(h2, clamp(s, 10, 92), 62),
      '--c-end': hslToHex(h + 50, clamp(s * 0.9, 10, 80), 46),
      '--c-tint-50': hslToHex(h, clamp(s, 8, 100), 96.5),
      '--c-tint-100': hslToHex(h, clamp(s, 8, 100), 93.5),
      '--c-tint-200': hslToHex(h, clamp(s, 8, 100), 89.5)
    };
    pal['--c-primary-rgb'] = hexToRgb(primary).join(',');
    pal['--accent-rgb'] = hexToRgb(accent).join(',');
    pal['--accent-soft-rgb'] = hexToRgb(soft).join(',');
    pal['--c-vio-rgb'] = hexToRgb(pal['--c-vio-3']).join(',');
    return pal;
  }


  /* ───────── penyelarasan semua warna komponen ─────────
     Warna status (success/warning/danger/info) bergeser tipis ke arah warna
     tema (tetap terbaca sebagai hijau/kuning/merah/biru muda), warna ekstra
     (ungu, pink, cyan, teal, oranye, lime) berputar penuh mengikuti hue tema,
     dan abu-abu/permukaan gelap diberi rona tema. Kecerahan (luminance) tiap
     warna dipertahankan, jadi kontras teks/latar tetap sama seperti desain awal. */
  var STATUS_LIMIT = { success: 15, warning: 12, danger: 10, info: 18 };

  function matchLuminance(h, s, target) {
    var lo = 0, hi = 100, i;
    for (i = 0; i < 22; i++) {
      var mid = (lo + hi) / 2;
      if (luminance(hslToHex(h, s, mid)) < target) lo = mid; else hi = mid;
    }
    return hslToHex(h, s, (lo + hi) / 2);
  }

  function harmonize(primaryHex) {
    var p = hexToHsl(primaryHex);
    var dh = ((p.h - 243 + 540) % 360) - 180;             // selisih hue terhadap indigo bawaan
    var k = clamp(p.s / 76, 0.6, 1.15);                   // tema pucat -> warna lain ikut lebih kalem
    var out = {};
    Object.keys(GROUPS).forEach(function (g) {
      GROUPS[g].forEach(function (hex0) {
        var hex = '#' + hex0, o = hexToHsl(hex), h, s;
        if (g === 'neutral') { h = p.h; s = o.s * 0.75; }
        else if (STATUS_LIMIT[g]) { h = o.h + clamp(dh * 0.25, -STATUS_LIMIT[g], STATUS_LIMIT[g]); s = clamp(o.s * k, 0, 100); }
        else { h = o.h + dh; s = clamp(o.s * k, 0, 100); }
        var nh = matchLuminance(h, s, luminance(hex));
        out['--k-' + hex0] = nh;
        out['--k-' + hex0 + '-rgb'] = hexToRgb(nh).join(',');
      });
    });
    return out;
  }

  function getHarmony() {
    try { return localStorage.getItem(HARMONY_KEY) !== '0'; } catch (e) { return true; }
  }

  /* ───────── terapkan / simpan ───────── */
  var root = document.documentElement;
  var current = DEFAULT_HEX;
  var listeners = [];

  function apply(hex, persist) {
    var h = normHex(hex) || DEFAULT_HEX;
    current = h;
    // bersihkan dulu; default = nilai asli di dashboard.css -> tampilan 100% sama seperti semula
    VARS.concat(K_VARS).forEach(function (v) { root.style.removeProperty(v); });
    if (h !== DEFAULT_HEX) {
      var pal = buildPalette(h);
      Object.keys(pal).forEach(function (k) { root.style.setProperty(k, pal[k]); });
      if (getHarmony()) {
        var kv = harmonize(pal['--c-primary']);
        Object.keys(kv).forEach(function (k) { root.style.setProperty(k, kv[k]); });
      }
    }
    if (persist) {
      try {
        if (h === DEFAULT_HEX) localStorage.removeItem(STORAGE_KEY);
        else localStorage.setItem(STORAGE_KEY, h);
      } catch (e) {}
    }
    listeners.forEach(function (fn) { try { fn(h); } catch (e) {} });
  }

  function getSaved() {
    try { return normHex(localStorage.getItem(STORAGE_KEY)); } catch (e) { return null; }
  }

  // Terapkan warna tersimpan SEKARANG (skrip ini dimuat di awal <body>) -> tanpa kedip.
  var saved = getSaved();
  if (saved) apply(saved, false);

  window.TarbiyahSystemTheme = {
    presets: PRESETS,
    defaultColor: DEFAULT_HEX,
    setColor: function (hex) { apply(hex, true); },
    getColor: function () { return current; },
    reset: function () { apply(DEFAULT_HEX, true); },
    getHarmony: getHarmony,
    setHarmony: function (on) {
      try { if (on) localStorage.removeItem(HARMONY_KEY); else localStorage.setItem(HARMONY_KEY, '0'); } catch (e) {}
      apply(current, false);
    },
    // hex bawaan desain -> hex yang sedang dipakai (untuk kode JS yang butuh nilai hex, mis. ikon notifikasi)
    resolve: function (hex) {
      var v = getComputedStyle(root).getPropertyValue('--k-' + String(hex).replace('#', '').toLowerCase()).trim();
      return v || hex;
    },
    primaryHex: function () { return getComputedStyle(root).getPropertyValue('--c-primary').trim() || DEFAULT_HEX; },
    onChange: function (fn) { listeners.push(fn); }
  };

  /* ───────── UI panel ───────── */
  function initPanel() {
    var panel = document.getElementById('themeSettings');
    if (!panel) return;

    var $ = function (sel) { return panel.querySelector(sel); };
    var swWrap = $('#tsSwatches');
    var picker = $('#tsPicker');
    var hexInput = $('#tsHex');
    var hexHelp = $('#tsHexHelp');
    var modeBtns = panel.querySelectorAll('[data-ts-mode]');

    var CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

    // Preset
    PRESETS.forEach(function (p) {
      var cell = document.createElement('div');
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'ts-swatch'; b.dataset.hex = p.hex;
      b.style.setProperty('--sw', p.hex);
      b.title = p.name; b.setAttribute('aria-label', 'Warna ' + p.name);
      b.innerHTML = CHECK;
      b.addEventListener('click', function () { TarbiyahSystemTheme.setColor(p.hex); });
      var n = document.createElement('span');
      n.className = 'ts-swatch-name'; n.textContent = p.name;
      cell.appendChild(b); cell.appendChild(n);
      swWrap.appendChild(cell);
    });

    function syncColor(hex) {
      Array.prototype.forEach.call(swWrap.querySelectorAll('.ts-swatch'), function (b) {
        var on = b.dataset.hex === hex;
        b.classList.toggle('active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      picker.value = hex;
      if (document.activeElement !== hexInput) hexInput.value = hex.toUpperCase();
      hexInput.classList.remove('is-invalid');
      var used = TarbiyahSystemTheme.primaryHex();
      hexHelp.textContent = used.toLowerCase() !== hex.toLowerCase()
        ? 'Warna disesuaikan otomatis menjadi ' + used.toUpperCase() + ' agar teks putih tetap terbaca.'
        : 'Berlaku di tema terang dan gelap.';
    }
    TarbiyahSystemTheme.onChange(syncColor);
    syncColor(TarbiyahSystemTheme.getColor());

    // Warna kustom
    picker.addEventListener('input', function () { TarbiyahSystemTheme.setColor(picker.value); });
    hexInput.addEventListener('input', function () {
      var h = normHex(hexInput.value);
      hexInput.classList.toggle('is-invalid', !h);
      if (h) TarbiyahSystemTheme.setColor(h);
    });
    hexInput.addEventListener('blur', function () { syncColor(TarbiyahSystemTheme.getColor()); });

    // Selaraskan semua warna komponen (status, ekstra, abu-abu)
    var harm = $('#tsHarmony');
    if (harm) {
      harm.checked = TarbiyahSystemTheme.getHarmony();
      harm.addEventListener('change', function () { TarbiyahSystemTheme.setHarmony(harm.checked); });
    }

    // Reset
    $('#tsReset').addEventListener('click', function () {
      TarbiyahSystemTheme.reset();
      if (harm) { harm.checked = true; try { localStorage.removeItem(HARMONY_KEY); } catch (e) {} }
      if (window.showToast) window.showToast('info', { title: 'Warna direset', msg: 'Kembali ke warna bawaan (Indigo).', duration: 2500 });
    });

    // Mode terang / gelap — sinkron dengan tombol bulan/matahari di topbar
    function isDark() { return root.getAttribute('data-theme') === 'dark'; }
    function syncMode() {
      var dark = isDark();
      Array.prototype.forEach.call(modeBtns, function (b) {
        var on = (b.dataset.tsMode === 'dark') === dark;
        b.classList.toggle('active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }
    Array.prototype.forEach.call(modeBtns, function (b) {
      b.addEventListener('click', function () {
        var dark = b.dataset.tsMode === 'dark';
        if (dark) root.setAttribute('data-theme', 'dark'); else root.removeAttribute('data-theme');
        try { localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light'); } catch (e) {}
      });
    });
    new MutationObserver(syncMode).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    syncMode();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPanel);
  else initPanel();
})();
