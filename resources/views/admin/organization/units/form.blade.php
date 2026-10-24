@extends('admin._layout')
@php($isEdit = $unit->exists)
@section('title', $isEdit ? 'Ubah Unit Organisasi' : 'Tambah Unit Organisasi')
@section('parent', 'Unit Organisasi')
@section('parent_url', route('admin.organization.units.index'))
@section('content')
  @include('partials.admin.select2-assets')
  @php
    $parentData = $parents->map(fn ($p) => [
        'id' => $p->id, 'name' => $p->name, 'code' => $p->code, 'path' => $p->path,
        'depth' => substr_count((string) $p->path, ' › '), 'type' => $p->type?->slug,
    ])->values();
    $typeData = $types->map(fn ($t) => ['id' => $t->id, 'slug' => $t->slug, 'name' => $t->name])->values();
    $errorStep = collect(['type_id' => 1, 'parent_id' => 2, 'name' => 3, 'code' => 3, 'status' => 3, 'address' => 4, 'description' => 4])
        ->filter(fn ($step, $field) => $errors->has($field))->min();
  @endphp
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'units', 'compact' => true])</div>

  <div class="card border-0 shadow-sm wz">
    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
      <h5 class="mb-1">{{ $isEdit ? 'Ubah data unit' : 'Tambah unit organisasi' }}</h5>
      <p class="text-secondary mb-3 small">Isi berurutan dari langkah 1. Setiap langkah diperiksa sebelum lanjut, jadi Anda langsung tahu bila ada yang kurang.</p>
      <ol class="wz-steps" id="wzSteps" aria-label="Langkah pengisian">
        @foreach ([1 => 'Jenis unit', 2 => 'Unit induk', 3 => 'Identitas', 4 => 'Detail & tinjau'] as $n => $label)
          <li class="wz-step" data-step="{{ $n }}">
            <button type="button" class="wz-dot" data-goto="{{ $n }}" aria-label="Langkah {{ $n }}: {{ $label }}"><span class="wz-num">{{ $n }}</span></button>
            <span class="wz-label">{{ $label }}</span>
          </li>
        @endforeach
      </ol>
    </div>

    <div class="card-body p-4">
      <form method="POST" id="wzForm" novalidate
            action="{{ $isEdit ? route('admin.organization.units.update', $unit) : route('admin.organization.units.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif

        @if ($errors->any())
          <div class="alert alert-danger small" role="alert" id="wzServerErrors">
            <div class="fw-semibold mb-1">Data belum dapat disimpan. Perbaiki isian berikut:</div>
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
          </div>
        @endif

        {{-- Langkah 1: jenis --}}
        <section class="wz-panel" data-step="1" aria-labelledby="wz-h1">
          <h6 id="wz-h1" class="mb-1">1. Pilih jenis unit</h6>
          <p class="text-secondary small">Jenis menentukan posisi unit dalam hierarki, sehingga langkah berikutnya menyesuaikan.</p>
          <label for="type_id" class="form-label fw-semibold">Jenis unit organisasi <span class="text-danger">*</span></label>
          <select id="type_id" name="type_id" data-placeholder="Cari atau pilih jenis unit…" class="form-select form-select-lg @error('type_id') is-invalid @enderror">
            <option value=""></option>
            @foreach($types as $type)
              <option value="{{ $type->id }}" @selected(old('type_id', $unit->type_id) == $type->id)>{{ $type->name }}</option>
            @endforeach
          </select>
          <div class="invalid-feedback" data-fb="type_id" @error('type_id') style="display:block" @enderror>@error('type_id'){{ $message }}@enderror</div>
          <div class="alert alert-info mt-3 mb-0" aria-live="polite">
            <strong id="guidance-title">Pilih jenis unit</strong>
            <div id="guidance-text" class="small mt-1">Petunjuk pengisian akan muncul sesuai jenis unit yang dipilih.</div>
          </div>
        </section>

        {{-- Langkah 2: induk --}}
        <section class="wz-panel" data-step="2" aria-labelledby="wz-h2" hidden>
          <h6 id="wz-h2" class="mb-1">2. Pilih unit induk</h6>
          <p class="text-secondary small" id="parent-help">Unit induk adalah unit di atasnya dalam bagan.</p>
          <label for="parent_id" class="form-label fw-semibold">Unit induk <span class="text-danger" id="parent-req">*</span></label>
          <select id="parent_id" name="parent_id" data-placeholder="Ketik nama atau kode unit induk…" class="form-select js-unit-select @error('parent_id') is-invalid @enderror">
            <option value=""></option>
          </select>
          <div class="invalid-feedback" data-fb="parent_id" @error('parent_id') style="display:block" @enderror>@error('parent_id'){{ $message }}@enderror</div>
          <div class="alert alert-warning small mt-3 mb-0 d-none" id="parent-empty">Belum ada unit induk yang sesuai dan valid. Buat unit induknya terlebih dahulu.</div>
        </section>

        {{-- Langkah 3: identitas --}}
        <section class="wz-panel" data-step="3" aria-labelledby="wz-h3" hidden>
          <h6 id="wz-h3" class="mb-1">3. Identitas unit</h6>
          <p class="text-secondary small mb-3" id="wz-context"></p>
          <div class="row g-3">
            <div class="col-md-7">
              <label for="name" class="form-label fw-semibold"><span id="name-label">Nama unit</span> <span class="text-danger">*</span></label>
              <input id="name" name="name" value="{{ old('name', $unit->name) }}" maxlength="160" autocomplete="off" class="form-control @error('name') is-invalid @enderror">
              <div class="invalid-feedback" data-fb="name" @error('name') style="display:block" @enderror>@error('name'){{ $message }}@enderror</div>
            </div>
            <div class="col-md-5">
              <label for="code" class="form-label fw-semibold">Kode unit <span class="text-danger">*</span></label>
              <input id="code" name="code" value="{{ old('code', $unit->code) }}" maxlength="40" autocomplete="off" spellcheck="false" class="form-control text-uppercase @error('code') is-invalid @enderror">
              <div class="invalid-feedback" data-fb="code" @error('code') style="display:block" @enderror>@error('code'){{ $message }}@enderror</div>
              <div class="form-text" id="code-hint">Unik, huruf/angka dengan tanda hubung. Contoh: <span id="code-example">UNIT-001</span></div>
              <button type="button" class="btn btn-link btn-sm p-0 d-none" id="code-suggest"></button>
            </div>
            <div class="col-12">
              <div class="form-label fw-semibold mb-1">Status</div>
              <div class="btn-group" role="group" aria-label="Status unit">
                <input type="radio" class="btn-check" name="status" id="st-active" value="active" @checked(old('status', $unit->status ?: 'active') === 'active')>
                <label class="btn btn-outline-success" for="st-active">Aktif</label>
                <input type="radio" class="btn-check" name="status" id="st-inactive" value="inactive" @checked(old('status', $unit->status) === 'inactive')>
                <label class="btn btn-outline-secondary" for="st-inactive">Nonaktif</label>
              </div>
            </div>
          </div>
        </section>

        {{-- Langkah 4: detail + tinjau --}}
        <section class="wz-panel" data-step="4" aria-labelledby="wz-h4" hidden>
          <h6 id="wz-h4" class="mb-1">4. Detail tambahan &amp; tinjau</h6>
          <p class="text-secondary small">Alamat dan keterangan bersifat opsional. Periksa ringkasan di bawah sebelum menyimpan.</p>
          <div class="row g-3">
            <div class="col-12">
              <label for="address" class="form-label">Alamat / lokasi kantor <span class="text-secondary">(opsional)</span></label>
              <input id="address" name="address" value="{{ old('address', $unit->address) }}" maxlength="500" class="form-control @error('address') is-invalid @enderror" placeholder="Alamat kantor atau lokasi unit">
              <div class="invalid-feedback" data-fb="address" @error('address') style="display:block" @enderror>@error('address'){{ $message }}@enderror</div>
            </div>
            <div class="col-12">
              <label for="description" class="form-label">Keterangan <span class="text-secondary">(opsional)</span></label>
              <textarea id="description" name="description" rows="3" maxlength="10000" class="form-control @error('description') is-invalid @enderror" placeholder="Tugas, cakupan, atau keterangan unit">{{ old('description', $unit->description) }}</textarea>
              <div class="d-flex justify-content-between">
                <div class="invalid-feedback" data-fb="description" @error('description') style="display:block" @enderror>@error('description'){{ $message }}@enderror</div>
                <div class="form-text ms-auto"><span id="desc-count">0</span>/10.000</div>
              </div>
            </div>
          </div>
          <div class="wz-review mt-4" aria-live="polite">
            <div class="fw-semibold mb-2">Ringkasan</div>
            <dl class="row mb-0 small">
              <dt class="col-sm-3">Jenis unit</dt><dd class="col-sm-9" id="rv-type"></dd>
              <dt class="col-sm-3">Unit induk</dt><dd class="col-sm-9" id="rv-parent"></dd>
              <dt class="col-sm-3">Nama</dt><dd class="col-sm-9" id="rv-name"></dd>
              <dt class="col-sm-3">Kode</dt><dd class="col-sm-9" id="rv-code"></dd>
              <dt class="col-sm-3">Status</dt><dd class="col-sm-9" id="rv-status"></dd>
            </dl>
          </div>
        </section>

        <div class="d-flex flex-wrap justify-content-between gap-2 mt-4 pt-3 border-top">
          @if($isEdit)
            <a href="{{ route('admin.organization.units.show', $unit) }}" class="btn btn-light">Kembali ke detail</a>
          @else
            <a href="{{ route('admin.organization.units.index') }}" class="btn btn-light">Batal</a>
          @endif
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary d-none" id="wzBack">‹ Kembali</button>
            <button type="button" class="btn btn-primary" id="wzNext">Lanjut ›</button>
            <button type="submit" class="btn btn-success d-none" id="wzSubmit"><i class="bi bi-check2-circle me-1"></i>{{ $isEdit ? 'Simpan perubahan' : 'Simpan unit organisasi' }}</button>
          </div>
        </div>
      </form>
    </div>
  </div>

@push('scripts')
<style>
  .wz-steps { list-style: none; display: flex; gap: 0; margin: 0 0 1rem; padding: 0; }
  .wz-step { flex: 1; position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; }
  .wz-step::before { content: ""; position: absolute; top: 17px; left: -50%; width: 100%; height: 2px; background: var(--bs-border-color); z-index: 0; }
  .wz-step:first-child::before { display: none; }
  .wz-step.is-done::before, .wz-step.is-active::before { background: var(--bs-primary); }
  .wz-dot { position: relative; z-index: 1; width: 36px; height: 36px; border-radius: 50%; border: 2px solid var(--bs-border-color);
    background: var(--bs-body-bg); color: var(--bs-secondary-color); font-weight: 600; display: grid; place-items: center; padding: 0; }
  .wz-dot:disabled { cursor: not-allowed; opacity: .7; }
  .wz-step.is-active .wz-dot { border-color: var(--bs-primary); color: var(--bs-primary); box-shadow: 0 0 0 .25rem rgba(var(--bs-primary-rgb), .15); }
  .wz-step.is-done .wz-dot { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
  .wz-step.is-done .wz-num { display: none; }
  .wz-step.is-done .wz-dot::after { content: "✓"; }
  .wz-step.is-error .wz-dot { border-color: var(--bs-danger); color: var(--bs-danger); background: var(--bs-body-bg); }
  .wz-step.is-error .wz-dot::after { content: none; }
  .wz-step.is-error .wz-num { display: inline; }
  .wz-step.is-skipped .wz-dot { border-style: dashed; opacity: .6; }
  .wz-label { font-size: .78rem; margin-top: .35rem; color: var(--bs-secondary-color); }
  .wz-step.is-active .wz-label { color: var(--bs-body-color); font-weight: 600; }
  .wz-panel[hidden] { display: none; }
  .wz-panel { animation: wz-in .18s ease; }
  .wz-review { background: var(--bs-tertiary-bg); border-radius: .6rem; padding: .85rem 1rem; }
  .wz .invalid-feedback { display: none; }
  .wz .is-invalid ~ .select2-container--bootstrap-5 .select2-selection { border-color: var(--bs-form-invalid-border-color, #dc3545); }
  @keyframes wz-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
  @media (max-width: 575.98px) { .wz-label { display: none; } .wz-step.is-active .wz-label { display: block; position: absolute; top: 40px; white-space: nowrap; } .wz-steps { margin-bottom: 1.6rem; } }
  @media (prefers-reduced-motion: reduce) { .wz-panel { animation: none; } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var $ = window.jQuery;
  var levels = @json($levels);
  var types = @json($typeData);
  var allParents = @json($parentData);
  var isEdit = @json($isEdit);
  var rootAllowed = @json($canCreateRoot || ($isEdit && $unit->parent_id === null));
  var checkUrl = @json(route('admin.organization.units.check-code'));
  var ignoreId = @json($isEdit ? $unit->id : null);
  var oldParent = @json((string) old('parent_id', $unit->parent_id));
  var serverErrorStep = @json($errorStep);
  var CODE_RE = /^[A-Za-z0-9][A-Za-z0-9._-]*$/;

  var el = function (id) { return document.getElementById(id); };
  var typeSel = el('type_id'), parentSel = el('parent_id'), nameEl = el('name'), codeEl = el('code');
  var addrEl = el('address'), descEl = el('description');
  var panels = Array.prototype.slice.call(document.querySelectorAll('.wz-panel'));
  var stepEls = Array.prototype.slice.call(document.querySelectorAll('.wz-step'));
  var btnBack = el('wzBack'), btnNext = el('wzNext'), btnSubmit = el('wzSubmit');
  var current = 1, maxReached = isEdit ? 4 : 1, done = {}, codeState = { value: '', status: 'idle' }, codeTimer = null;

  function typeById(id) { return types.filter(function (t) { return String(t.id) === String(id); })[0] || null; }
  function typeNameBySlug(slug) { var t = types.filter(function (x) { return x.slug === slug; })[0]; return t ? t.name : slug; }
  function currentType() { return typeById(typeSel.value); }
  function currentLevel() { var t = currentType(); return t ? (levels[t.slug] || null) : null; }
  function allowedSlugs() { var lv = currentLevel(); return lv ? lv.parents : null; }   // null = bebas, [] = tingkat teratas
  function isTopLevel() { var a = allowedSlugs(); return a !== null && a.length === 0; }
  function parentRequired() { if (!currentType() || isTopLevel()) return false; var a = allowedSlugs(); return a !== null || !rootAllowed; }
  function skipParent() { return !!currentType() && isTopLevel(); }
  function val(e) { return (e.value || '').trim(); }

  // ---------- select2 ----------
  if ($ && $.fn.select2) {
    $(typeSel).select2({
      placeholder: typeSel.getAttribute('data-placeholder'), minimumResultsForSearch: 0,
      templateResult: function (s) {
        if (!s.id) return s.text;
        var lv = levels[(typeById(s.id) || {}).slug];
        var $r = $('<div class="s2-unit"></div>').append($('<div class="s2-unit-name"></div>').text(s.text));
        if (lv) $r.append($('<div class="s2-unit-meta"></div>').text(lv.parents.length ? 'Di bawah: ' + lv.parents.map(typeNameBySlug).join(', ') : 'Tingkat teratas'));
        return $r;
      }
    });
  }

  // ---------- induk ----------
  function rebuildParents() {
    var a = allowedSlugs(), keep = parentSel.value || oldParent, count = 0;
    while (parentSel.options.length > 1) parentSel.remove(1);
    allParents.forEach(function (p) {
      if (a && a.length && a.indexOf(p.type) === -1) return;
      var o = document.createElement('option');
      o.value = p.id; o.textContent = p.name + ' · ' + p.code;
      o.dataset.name = p.name; o.dataset.code = p.code; o.dataset.path = p.path; o.dataset.depth = 0;
      if (String(p.id) === String(keep)) o.selected = true;
      parentSel.appendChild(o); count++;
    });
    if (!parentSel.selectedOptions.length || !parentSel.value) parentSel.value = '';
    if ($) $(parentSel).trigger('change.select2');
    el('parent-empty').classList.toggle('d-none', !(parentRequired() && count === 0));
    var names = a && a.length ? a.map(typeNameBySlug).join(' / ') : null;
    el('parent-help').textContent = names ? 'Untuk jenis ini, induknya harus ' + names + '. Hanya unit yang sesuai yang ditampilkan.' : 'Pilih unit di atasnya dalam bagan' + (parentRequired() ? '.' : ' bila ada (opsional).');
    el('parent-req').classList.toggle('d-none', !parentRequired());
    parentSel.disabled = isTopLevel();
    return count;
  }

  // ---------- teks sesuai jenis ----------
  function applyType() {
    var t = currentType(), lv = currentLevel();
    el('guidance-title').textContent = lv ? lv.title : (t ? t.name : 'Pilih jenis unit');
    el('guidance-text').textContent = lv ? lv.guidance : (t ? 'Jenis unit ini tidak memiliki aturan khusus. Pilih unit induk bila ada.' : 'Petunjuk pengisian akan muncul sesuai jenis unit yang dipilih.');
    el('name-label').textContent = lv ? lv.name_label : 'Nama unit';
    nameEl.placeholder = lv ? lv.name_placeholder : 'Contoh: Nama unit organisasi';
    codeEl.placeholder = lv ? lv.code_placeholder : 'Contoh: UNIT-001';
    el('code-example').textContent = (lv ? lv.code_placeholder : 'Contoh: UNIT-001').replace(/^Contoh:\s*/, '');
    rebuildParents();
    updateContext(); updateSuggestion(); renumber();
  }
  function updateContext() {
    var p = parentSel.selectedOptions[0];
    el('wz-context').textContent = (currentType() ? currentType().name : '') + (p && p.value ? ' · di bawah ' + p.dataset.name : (currentType() ? ' · tingkat teratas' : ''));
  }

  // ---------- saran kode ----------
  var STOP = ['komisaris', 'wilayah', 'daerah', 'kabupaten', 'kota', 'kab', 'yayasan', 'pengurus', 'besar', 'komwil', 'komda', 'sekolah', 'majelis', 'unit', 'pendidikan'];
  function abbr(name) {
    var words = name.toUpperCase().split(/[^A-Z0-9]+/).filter(Boolean);
    var core = words.filter(function (w) { return STOP.indexOf(w.toLowerCase()) === -1; });
    var w = (core[0] || words[0] || '');
    if (w.length <= 3) return w;
    var out = w[0], rest = w.slice(1).replace(/[AIUEO]/g, '');
    out += rest.slice(0, 2);
    return out;
  }
  function suggestion() {
    var t = currentType(); if (!t || val(nameEl).length < 2) return '';
    var title = levels[t.slug] ? levels[t.slug].title : '';
    var m = /\(([A-Z]+)\)/.exec(title), prefix;
    if (m) prefix = m[1];
    else if (t.slug.indexOf('-') > -1) prefix = t.slug.split('-').map(function (x) { return x[0]; }).join('').toUpperCase();
    else prefix = t.name.toUpperCase().replace(/[^A-Z0-9]+/g, '-').slice(0, 10);
    var p = parentSel.selectedOptions[0], parentPart = '';
    if (p && p.value && p.dataset.code) { var segs = p.dataset.code.split('-'); parentPart = segs.length > 1 ? segs.slice(1).join('-') : ''; }
    return [prefix, parentPart, abbr(val(nameEl))].filter(Boolean).join('-').slice(0, 40);
  }
  function updateSuggestion() {
    var b = el('code-suggest'), s = suggestion();
    if (!s || (val(codeEl) !== '' && val(codeEl).toUpperCase() === s)) { b.classList.add('d-none'); return; }
    b.textContent = 'Gunakan saran kode: ' + s; b.dataset.value = s; b.classList.remove('d-none');
  }
  el('code-suggest').addEventListener('click', function () { codeEl.value = this.dataset.value; onCodeInput(); codeEl.focus(); });

  // ---------- cek kode unik ----------
  function setCodeHint(text, cls) { var h = el('code-hint'); h.className = 'form-text ' + (cls || ''); h.textContent = text; }
  function onCodeInput() {
    var up = codeEl.value.toUpperCase(); if (codeEl.value !== up) codeEl.value = up;
    clearFieldError('code'); updateSuggestion();
    var v = val(codeEl); clearTimeout(codeTimer);
    if (v === '' || !CODE_RE.test(v)) { codeState = { value: v, status: 'idle' }; setCodeHint('Unik, huruf/angka dengan tanda hubung. Contoh: ' + el('code-example').textContent); return; }
    codeState = { value: v, status: 'checking' }; setCodeHint('Memeriksa ketersediaan kode…', 'text-secondary');
    codeTimer = setTimeout(function () {
      var url = checkUrl + '?code=' + encodeURIComponent(v) + (ignoreId ? '&ignore=' + ignoreId : '');
      fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (val(codeEl) !== v || typeof j.available !== 'boolean') return;
          codeState = { value: v, status: j.available ? 'ok' : 'taken' };
          if (j.available) setCodeHint('✓ Kode tersedia.', 'text-success'); else { setCodeHint('✗ Kode sudah dipakai unit lain.', 'text-danger'); }
        }).catch(function () { codeState = { value: v, status: 'unknown' }; setCodeHint('Unik, huruf/angka dengan tanda hubung.'); });
    }, 400);
  }

  // ---------- validasi ----------
  var rules = {
    1: function () { var e = []; if (!typeSel.value) e.push(['type_id', 'Pilih jenis unit terlebih dahulu.']); return e; },
    2: function () {
      var e = [];
      if (!currentType() || skipParent()) return e;
      if (parentRequired() && !parentSel.value) { var a = allowedSlugs(); e.push(['parent_id', 'Pilih unit induk' + (a && a.length ? ' (' + a.map(typeNameBySlug).join(' / ') + ')' : '') + '.']); }
      return e;
    },
    3: function () {
      var e = [], n = val(nameEl), c = val(codeEl);
      if (!n) e.push(['name', 'Nama unit wajib diisi.']); else if (n.length < 2) e.push(['name', 'Nama unit minimal 2 karakter.']); else if (n.length > 160) e.push(['name', 'Nama unit maksimal 160 karakter.']);
      if (!c) e.push(['code', 'Kode unit wajib diisi.']);
      else if (c.length > 40) e.push(['code', 'Kode unit maksimal 40 karakter.']);
      else if (!CODE_RE.test(c)) e.push(['code', 'Kode hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung (diawali huruf/angka).']);
      else if (codeState.status === 'taken' && codeState.value === c) e.push(['code', 'Kode "' + c + '" sudah dipakai unit lain. Gunakan kode yang berbeda.']);
      return e;
    },
    4: function () {
      var e = [];
      if (addrEl.value.length > 500) e.push(['address', 'Alamat maksimal 500 karakter.']);
      if (descEl.value.length > 10000) e.push(['description', 'Keterangan maksimal 10.000 karakter.']);
      return e;
    }
  };
  function fieldEl(f) { return el(f === 'type_id' ? 'type_id' : f); }
  function clearFieldError(f) {
    var inp = fieldEl(f); if (inp) inp.classList.remove('is-invalid');
    var fb = document.querySelector('[data-fb="' + f + '"]'); if (fb) { fb.style.display = 'none'; }
  }
  function showFieldErrors(list) {
    list.forEach(function (it) {
      var inp = fieldEl(it[0]); if (inp) inp.classList.add('is-invalid');
      var fb = document.querySelector('[data-fb="' + it[0] + '"]'); if (fb) { fb.textContent = it[1]; fb.style.display = 'block'; }
    });
  }
  function validateStep(n, show) {
    var errs = rules[n]();
    if (show) { ['type_id', 'parent_id', 'name', 'code', 'address', 'description'].forEach(function (f) {
      if ((n === 1 && f === 'type_id') || (n === 2 && f === 'parent_id') || (n === 3 && (f === 'name' || f === 'code')) || (n === 4 && (f === 'address' || f === 'description'))) clearFieldError(f);
    }); showFieldErrors(errs); }
    stepEls[n - 1].classList.toggle('is-error', errs.length > 0 && !!show);
    if (show && errs.length) { var first = fieldEl(errs[0][0]); if (first && first.focus && n !== 1 && n !== 2) first.focus(); }
    return errs.length === 0;
  }

  // ---------- navigasi ----------
  function nextOf(n) { var x = n + 1; if (x === 2 && skipParent()) x = 3; return Math.min(x, 4); }
  function prevOf(n) { var x = n - 1; if (x === 2 && skipParent()) x = 1; return Math.max(x, 1); }
  function renumber() { stepEls[1].classList.toggle('is-skipped', skipParent()); }
  function fillReview() {
    var t = currentType(), p = parentSel.selectedOptions[0];
    el('rv-type').textContent = t ? t.name : '—';
    el('rv-parent').textContent = skipParent() ? 'Tidak ada (tingkat teratas)' : (p && p.value ? p.dataset.path : '—');
    el('rv-name').textContent = val(nameEl) || '—';
    el('rv-code').textContent = val(codeEl) || '—';
    el('rv-status').textContent = document.getElementById('st-inactive').checked ? 'Nonaktif' : 'Aktif';
  }
  function go(n) {
    current = n;
    panels.forEach(function (p) { p.hidden = Number(p.dataset.step) !== n; });
    stepEls.forEach(function (s, i) {
      var k = i + 1;
      s.classList.toggle('is-active', k === n);
      s.classList.toggle('is-done', !!done[k] && k !== n);
      var dot = s.querySelector('.wz-dot');
      dot.disabled = !(isEdit || k <= maxReached) || (k === 2 && skipParent());
      if (k === n) dot.setAttribute('aria-current', 'step'); else dot.removeAttribute('aria-current');
    });
    btnBack.classList.toggle('d-none', n === 1);
    btnNext.classList.toggle('d-none', n === 4);
    btnSubmit.classList.toggle('d-none', n !== 4 && !isEdit);
    if (n === 4) fillReview();
    if (n === 3) { updateContext(); updateSuggestion(); }
    if (n === 3) nameEl.focus();
    if (n === 4 && !isEdit) addrEl.focus();
  }
  btnNext.addEventListener('click', function () {
    if (!validateStep(current, true)) return;
    done[current] = true;
    var n = nextOf(current); maxReached = Math.max(maxReached, n);
    if (n === 3 && current === 1) done[2] = true;
    go(n);
  });
  btnBack.addEventListener('click', function () { go(prevOf(current)); });
  stepEls.forEach(function (s) {
    s.querySelector('.wz-dot').addEventListener('click', function () {
      var target = Number(this.dataset.goto);
      if (target === current) return;
      if (target > current) { for (var k = current; k < target; k++) { if (k === 2 && skipParent()) continue; if (!validateStep(k, true)) { go(k); return; } done[k] = true; } }
      go(target);
    });
  });

  // kirim: periksa semua langkah berurutan, lompat ke langkah bermasalah
  el('wzForm').addEventListener('submit', function (e) {
    for (var k = 1; k <= 4; k++) {
      if (k === 2 && skipParent()) continue;
      if (!validateStep(k, true)) { e.preventDefault(); go(k); return; }
    }
    if (skipParent()) parentSel.disabled = true;   // tidak terkirim: induk dikosongkan
    btnSubmit.disabled = true; btnSubmit.textContent = 'Menyimpan…';
  });

  // ---------- peristiwa ----------
  function onTypeChange() {
    parentSel.value = ''; oldParent = '';
    clearFieldError('type_id'); clearFieldError('parent_id');
    applyType();
    if (!isEdit) { maxReached = 1; done = {}; stepEls.forEach(function (s) { s.classList.remove('is-done', 'is-error'); }); go(1); }
  }
  if ($) { $(typeSel).on('change', onTypeChange); $(parentSel).on('change', function () { clearFieldError('parent_id'); updateContext(); updateSuggestion(); }); }
  else { typeSel.addEventListener('change', onTypeChange); parentSel.addEventListener('change', updateSuggestion); }
  nameEl.addEventListener('input', function () { clearFieldError('name'); updateSuggestion(); });
  codeEl.addEventListener('input', onCodeInput);
  addrEl.addEventListener('input', function () { clearFieldError('address'); });
  descEl.addEventListener('input', function () { clearFieldError('description'); el('desc-count').textContent = descEl.value.length.toLocaleString('id-ID'); });

  // ---------- mulai ----------
  applyType();
  el('desc-count').textContent = descEl.value.length.toLocaleString('id-ID');
  if (val(codeEl)) onCodeInput();
  var start = 1;
  if (serverErrorStep) {
    start = serverErrorStep; maxReached = 4;
    for (var s = 1; s < start; s++) done[s] = true;
    stepEls[start - 1].classList.add('is-error');
  } else if (!isEdit) {
    for (var k = 1; k <= 3; k++) {
      if (k === 2 && skipParent()) { done[2] = true; continue; }
      if (!rules[k]().length) { done[k] = true; maxReached = Math.min(4, nextOf(k)); start = nextOf(k); } else break;
    }
    if (val(nameEl) === '' && start > 3) start = 3;
  }
  go(start);
});
</script>
@endpush
@endsection
