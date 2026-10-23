/*!
 * panel.js — perilaku layout admin TarbiyahSystem: collapse sidebar, submenu accordion,
 * tooltip & flyout (mode ciut), mode horizontal, pencarian menu, tema, toast, preloader.
 * Tanpa dependensi selain Bootstrap bundle (untuk dropdown/offcanvas).
 */
(function () {
  'use strict';
  var d = document, html = d.documentElement, body = d.body;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
  };
  var isDesktop = function () { return window.matchMedia('(min-width:992px)').matches; };

  /* ───────── Preloader ───────── */
  var pre = $('#preloader');
  function hidePre() {
    if (!pre) return;
    pre.style.opacity = '0'; pre.style.visibility = 'hidden';
    setTimeout(function () { pre.style.display = 'none'; }, 450);
  }
  if (d.readyState === 'complete') hidePre(); else window.addEventListener('load', hidePre);
  setTimeout(hidePre, 2500); // pengaman bila ada aset lambat

  /* ───────── Toast ───────── */
  var TOAST_ICON = {
    success: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    danger:  '<path d="M18 6 6 18M6 6l12 12"/>',
    warning: '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
    info:    '<path d="M12 16v-4M12 8h.01"/><circle cx="12" cy="12" r="10"/>',
    primary: '<path d="M12 16v-4M12 8h.01"/><circle cx="12" cy="12" r="10"/>',
    dark:    '<path d="M12 16v-4M12 8h.01"/><circle cx="12" cy="12" r="10"/>'
  };
  var wrap = $('#toastWrap');
  window.showToast = function (type, o) {
    if (!wrap) return;
    o = o || {}; type = TOAST_ICON[type] ? type : 'info';
    var dur = o.duration == null ? 5000 : o.duration;
    var t = d.createElement('div');
    t.className = 'lm-toast lm-toast-' + type;
    t.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    var row = d.createElement('div'); row.className = 'd-flex gap-2 p-3';
    var ic = d.createElement('span'); ic.className = 'lm-toast-icon';
    ic.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' + TOAST_ICON[type] + '</svg>';
    var box = d.createElement('div'); box.className = 'flex-grow-1 min-w-0';
    if (o.title) { var ti = d.createElement('div'); ti.className = 'lm-toast-title'; ti.textContent = o.title; box.appendChild(ti); }
    if (o.msg) { var m = d.createElement('div'); m.className = 'lm-toast-msg'; m.textContent = o.msg; box.appendChild(m); }
    var cl = d.createElement('button'); cl.type = 'button'; cl.className = 'btn-close'; cl.setAttribute('aria-label', 'Tutup');
    row.appendChild(ic); row.appendChild(box); row.appendChild(cl); t.appendChild(row);
    if (dur > 0) { var pg = d.createElement('div'); pg.className = 'lm-toast-progress'; pg.innerHTML = '<span style="--d:' + dur + 'ms"></span>'; t.appendChild(pg); }
    wrap.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('lm-in'); });
    var timer;
    function close() { clearTimeout(timer); t.classList.remove('lm-in'); t.classList.add('lm-out'); setTimeout(function () { t.remove(); }, 300); }
    function start() { if (dur > 0) timer = setTimeout(close, dur); }
    cl.addEventListener('click', close);
    t.addEventListener('mouseenter', function () { clearTimeout(timer); t.classList.add('lm-paused'); });
    t.addEventListener('mouseleave', function () { t.classList.remove('lm-paused'); start(); });
    start();
  };
  var flash = $('#flashData');
  if (flash) {
    if (flash.dataset.success) showToast('success', { title: 'Berhasil', msg: flash.dataset.success });
    if (flash.dataset.error) showToast('danger', { title: 'Gagal', msg: flash.dataset.error, duration: 8000 });
  }

  var sb = $('#sidebar');
  if (!sb) return; // halaman tanpa sidebar (mis. layout client)
  var main = $('#main'), top = $('#topbar'), bd = $('#sidebarBackdrop');
  var tip = $('#sidebarTooltip'), fly = $('#sidebarFlyout');

  /* ───────── Collapse sidebar (desktop vertikal) ───────── */
  var KEY_SB = 'TarbiyahSystem-sidebar', KEY_LAYOUT = 'TarbiyahSystem-layout';
  function horizontal() { return body.classList.contains('mode-horizontal'); }
  function applyCollapsed(on) {
    sb.classList.toggle('sidebar-collapsed', on);
    main.classList.toggle('collapsed', on);
    top.classList.toggle('collapsed', on);
    hideTip(); hideFly();
  }
  function wantCollapsed() { return isDesktop() && !horizontal() && store.get(KEY_SB) === 'collapsed'; }
  applyCollapsed(wantCollapsed());
  html.classList.remove('sidebar-pre-collapsed');

  var cb = $('#collapseBtn');
  if (cb) cb.addEventListener('click', function () {
    var on = !sb.classList.contains('sidebar-collapsed');
    store.set(KEY_SB, on ? 'collapsed' : 'expanded'); applyCollapsed(on);
  });

  /* ───────── Mobile off-canvas ───────── */
  function openMobile() { sb.classList.add('mobile-open'); bd.classList.add('visible'); }
  function closeMobile() { sb.classList.remove('mobile-open'); bd.classList.remove('visible'); }
  var mb = $('#mobileMenuBtn');
  if (mb) mb.addEventListener('click', openMobile);
  bd.addEventListener('click', closeMobile);
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMobile(); });
  $$('#sidebar a[href]').forEach(function (a) { a.addEventListener('click', closeMobile); });

  /* ───────── Submenu accordion (vertikal) ───────── */
  function setOpen(li, on) {
    li.classList.toggle('open', on);
    var sm = $('.submenu', li); if (sm) sm.classList.toggle('open', on);
  }
  $$('.menu-trigger', sb).forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (horizontal() || sb.classList.contains('sidebar-collapsed')) return;
      var li = btn.closest('.menu-item'), will = !li.classList.contains('open');
      $$('.menu-item.open', sb).forEach(function (o) { if (o !== li) setOpen(o, false); });
      setOpen(li, will);
    });
  });

  /* ───────── Tooltip & flyout (sidebar ciut) ───────── */
  var flyTimer;
  function hideTip() { if (tip) tip.classList.remove('visible'); }
  function hideFly() { if (fly) fly.classList.remove('visible'); }
  function esc(s) { var x = d.createElement('div'); x.textContent = s; return x.innerHTML; }
  $$('.menu-item', sb).forEach(function (li) {
    var trigger = $('.menu-trigger', li), link = trigger || $('.nav-item-link', li);
    if (!link) return;
    li.addEventListener('mouseenter', function () {
      if (horizontal()) { openH(li); return; }
      if (!sb.classList.contains('sidebar-collapsed')) return;
      clearTimeout(flyTimer);
      var r = link.getBoundingClientRect();
      if (trigger) {
        hideTip();
        var items = $$('.submenu a', li).map(function (a) { return '<a href="' + esc(a.getAttribute('href')) + '">' + esc(a.textContent.trim()) + '</a>'; }).join('');
        fly.innerHTML = '<div class="flyout-title">' + esc(link.getAttribute('data-tip') || '') + '</div>' + items;
        fly.style.left = (r.right + 8) + 'px'; fly.style.top = r.top + 'px'; fly.classList.add('visible');
      } else {
        hideFly();
        tip.textContent = link.getAttribute('data-tip') || '';
        tip.style.left = (r.right + 12) + 'px'; tip.style.top = (r.top + r.height / 2) + 'px'; tip.classList.add('visible');
      }
    });
    li.addEventListener('mouseleave', function () {
      if (horizontal()) { closeHSoon(li); return; }
      hideTip(); flyTimer = setTimeout(hideFly, 150);
    });
  });
  if (fly) {
    fly.addEventListener('mouseenter', function () { clearTimeout(flyTimer); });
    fly.addEventListener('mouseleave', function () { flyTimer = setTimeout(hideFly, 150); });
  }

  /* ───────── Mode horizontal (desktop) ───────── */
  var hTimers = new WeakMap();
  function openH(li) {
    var sm = $('.submenu', li); if (!sm) return;
    clearTimeout(hTimers.get(li));
    $$('.submenu.h-open', sb).forEach(function (o) { if (o !== sm) o.classList.remove('h-open'); });
    var r = li.getBoundingClientRect();
    sm.style.top = (r.bottom + 4) + 'px'; sm.style.left = r.left + 'px';
    sm.classList.add('h-open');
  }
  function closeHSoon(li) {
    var sm = $('.submenu', li); if (!sm) return;
    hTimers.set(li, setTimeout(function () { sm.classList.remove('h-open'); }, 140));
  }
  var lt = $('#layoutToggleBtn'), ltLabel = $('#layoutToggleLabel');
  function syncLayoutLabel() { if (ltLabel) ltLabel.textContent = horizontal() ? 'Vertikal' : 'Horizontal'; }
  function setHorizontal(on) {
    body.classList.toggle('mode-horizontal', on);
    $$('.submenu.h-open', sb).forEach(function (s) { s.classList.remove('h-open'); s.style.top = s.style.left = ''; });
    applyCollapsed(wantCollapsed()); syncLayoutLabel();
  }
  if (lt) lt.addEventListener('click', function () {
    var on = !horizontal(); store.set(KEY_LAYOUT, on ? 'horizontal' : 'vertical'); setHorizontal(on);
  });
  syncLayoutLabel();
  window.addEventListener('resize', function () { applyCollapsed(wantCollapsed()); });

  /* ───────── Tema gelap/terang ───────── */
  var tt = $('#themeToggleBtn');
  if (tt) tt.addEventListener('click', function () {
    var dark = html.getAttribute('data-theme') === 'dark';
    if (dark) html.removeAttribute('data-theme'); else html.setAttribute('data-theme', 'dark');
    store.set('TarbiyahSystem-theme', dark ? 'light' : 'dark');
  });

  /* ───────── Pencarian menu ───────── */
  var si = $('#searchInput'), sd = $('#searchDropdown');
  if (si && sd) {
    var index = $$('#sidebar a[href]').map(function (a) {
      var grp = a.closest('.menu-item') && $('.menu-trigger .label-text', a.closest('.menu-item'));
      var sect = a.closest('ul'); sect = sect && sect.previousElementSibling && sect.previousElementSibling.classList.contains('menu-eyebrow') ? sect.previousElementSibling.textContent : '';
      return { label: a.textContent.trim().replace(/\s+/g, ' '), href: a.getAttribute('href'), where: grp ? grp.textContent.trim() : sect };
    });
    var sel = -1;
    var render = function (q) {
      q = q.trim().toLowerCase();
      var hits = index.filter(function (i) { return !q || i.label.toLowerCase().indexOf(q) > -1 || (i.where || '').toLowerCase().indexOf(q) > -1; });
      sd.innerHTML = hits.length ? hits.map(function (h) { return '<a class="search-hit" href="' + esc(h.href) + '">' + esc(h.label) + '<small>' + esc(h.where || '') + '</small></a>'; }).join('') : '<div class="search-empty">Menu tidak ditemukan</div>';
      sd.classList.add('open'); sel = -1;
    };
    si.addEventListener('focus', function () { render(si.value); });
    si.addEventListener('input', function () { render(si.value); });
    si.addEventListener('keydown', function (e) {
      var hits = $$('.search-hit', sd);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); if (!hits.length) return;
        sel = (sel + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
        hits.forEach(function (h, i) { h.classList.toggle('sel', i === sel); });
      } else if (e.key === 'Enter') {
        e.preventDefault(); var h = hits[sel >= 0 ? sel : 0]; if (h) location.href = h.getAttribute('href');
      } else if (e.key === 'Escape') { sd.classList.remove('open'); si.blur(); }
    });
    d.addEventListener('click', function (e) { if (!e.target.closest('#searchWrapper')) sd.classList.remove('open'); });
    d.addEventListener('keydown', function (e) {
      if ((e.key === '/' || (e.key === 'k' && (e.ctrlKey || e.metaKey))) && !/INPUT|TEXTAREA|SELECT/.test((d.activeElement || {}).tagName)) { e.preventDefault(); si.focus(); }
    });
  }

  if (horizontal()) setHorizontal(true);
})();
