{{-- Wizard "Tambah unit" (dipakai di Ikhtisar dan Unit Organisasi).
     Urutan: 1 Unit induk -> 2 Jenis unit (menyesuaikan induk) -> 3 Identitas -> 4 Detail & tinjau.
     Variabel: $addable, $rootTypes, $parentChoices, $unitNames; opsional $returnTo (organization|units). --}}
@include('partials.admin.select2-assets')
@php
  $parentChoices = $parentChoices ?? [];
  $returnTo = $returnTo ?? 'organization';
  $catalog = collect($addable ?? [])->flatten(1)->merge($rootTypes ?? [])->unique('id')->keyBy('id')->all();
  $rootTypeIds = collect($rootTypes ?? [])->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
  $hasParentOptions = ! empty($parentChoices) || ! empty($rootTypes);
  $activeTypeCount = \App\Models\OrganizationalUnitType::query()->where('is_active', true)->count();
  $canManageTypes = auth('admin')->user()?->can('organization.types.manage') ?? false;
  $reopen = null;
  if (old('from_modal')) {
      $oldParent = old('parent_id');
      $reopen = [
          'parent' => ($oldParent !== null && $oldParent !== '') ? (string) $oldParent : 'root',
          'type_id' => old('type_id'),
          'name' => old('name'),
          'code' => old('code'),
          'status' => old('status', 'active'),
          'address' => old('address'),
          'description' => old('description'),
          'errors' => $errors->messages(),
      ];
  }
@endphp
@can('organization.units.manage')
  <div class="modal fade" id="orgAddModal" tabindex="-1" aria-labelledby="orgAddTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <form method="POST" action="{{ route('admin.organization.units.store') }}" class="modal-content" id="orgAddForm" novalidate>
        @csrf
        <input type="hidden" name="from_modal" value="1">
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <input type="hidden" name="parent_id" id="orgAddParentVal" value="">

        <div class="modal-header border-0 pb-0">
          <div>
            <h5 class="modal-title" id="orgAddTitle">Tambah unit organisasi</h5>
            <div class="small text-secondary" id="orgAddSubtitle">Isi berurutan dari langkah 1. Setiap langkah diperiksa sebelum lanjut.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body">
          <ol class="ow-steps" id="orgAddSteps" aria-label="Langkah pengisian">
            @foreach ([1 => 'Unit induk', 2 => 'Jenis unit', 3 => 'Identitas', 4 => 'Detail & tinjau'] as $n => $label)
              <li class="ow-step" data-step="{{ $n }}">
                <button type="button" class="ow-dot" data-goto="{{ $n }}" aria-label="Langkah {{ $n }}: {{ $label }}"><span class="ow-num">{{ $n }}</span></button>
                <span class="ow-label">{{ $label }}</span>
              </li>
            @endforeach
          </ol>

          <div class="alert alert-danger small d-none" role="alert" id="orgAddServerErrors">
            <div class="fw-semibold mb-1">Data belum dapat disimpan. Perbaiki isian berikut:</div>
            <ul class="mb-0 ps-3" id="orgAddServerList"></ul>
          </div>

          {{-- Langkah 1 --}}
          <section class="ow-panel" data-step="1">
            <h6 class="mb-1">1. Di bawah unit mana unit baru ditempatkan?</h6>
            <p class="text-secondary small">Pilih unit induk lebih dulu. Jenis unit di langkah berikutnya menyesuaikan pilihan ini, jadi hanya kombinasi yang sah yang tampil.</p>
            @unless ($hasParentOptions)
              <div class="alert alert-warning small" role="alert" id="orgAddNoParent">
                @if ($activeTypeCount === 0)
                  <div class="fw-semibold mb-1">Belum ada jenis unit aktif</div>
                  <div>Unit belum bisa dibuat sebelum ada jenis unit (mis. Pengurus Besar, KOMWIL, KOMDA). Buat jenis unitnya lebih dulu.</div>
                  @if ($canManageTypes)
                    <a href="{{ route('admin.organization.unit-types.create') }}" class="btn btn-sm btn-warning mt-2">Buat jenis unit</a>
                  @else
                    <div class="mt-1">Hubungi pengelola yang berwenang mengelola jenis unit.</div>
                  @endif
                @else
                  <div class="fw-semibold mb-1">Belum ada unit induk yang bisa diberi turunan</div>
                  <div>Unit induk belum dibuat, berstatus nonaktif, hierarkinya bermasalah, atau di luar cakupan akses Anda. Buat atau aktifkan unit induknya lebih dulu, lalu kembali ke sini.</div>
                  <a href="{{ route('admin.organization.units.index', ['view' => 'tree']) }}" class="btn btn-sm btn-warning mt-2">Lihat bagan unit</a>
                @endif
              </div>
            @endunless
            <label for="orgAddParentPick" class="form-label fw-semibold">Unit induk <span class="text-danger">*</span></label>
            <select id="orgAddParentPick" class="form-select" data-placeholder="Ketik nama atau kode unit induk…" @disabled(! $hasParentOptions)>
              <option value=""></option>
              @if ($rootTypes ?? [])
                <option value="root" data-name="Tanpa induk" data-code="" data-path="Unit tingkat teratas" data-depth="0">Tanpa induk (unit tingkat teratas)</option>
              @endif
              @foreach ($parentChoices as $choice)
                <option value="{{ $choice['id'] }}" data-name="{{ $choice['name'] }}" data-code="{{ $choice['code'] }}"
                        data-path="{{ $choice['path'] }}" data-depth="{{ $choice['depth'] }}">{{ $choice['name'] }} · {{ $choice['code'] }}</option>
              @endforeach
            </select>
            <div class="invalid-feedback" data-fb="parent_id"></div>
            <div class="form-text text-primary d-none" id="orgAddFilterNote"></div>
            @if ($hasParentOptions)
              <div class="form-text mt-2">
                Induk yang dicari tidak ada? Unit induk harus dibuat lebih dulu dan berstatus aktif.
                @if ($rootTypes ?? [])
                  Untuk unit tingkat paling atas, <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="orgAddPickRoot">mulai tanpa induk</button>.
                @endif
              </div>
            @endif
          </section>

          {{-- Langkah 2 --}}
          <section class="ow-panel" data-step="2" hidden>
            <h6 class="mb-1">2. Pilih jenis unit</h6>
            <p class="text-secondary small" id="orgAddTypeHelp">Hanya jenis yang diizinkan di bawah unit induk yang tampil.</p>
            <label for="orgAddType" class="form-label fw-semibold">Jenis unit <span class="text-danger">*</span></label>
            <select id="orgAddType" name="type_id" class="form-select" data-placeholder="Cari atau pilih jenis unit…">
              <option value=""></option>
            </select>
            <div class="invalid-feedback" data-fb="type_id"></div>
            <div class="form-text mt-2">
              Jenis yang dicari tidak tampil? Pilih unit induk lain di langkah 1 (jenis mengikuti induk)
              @if ($canManageTypes)
                atau cek menu <a href="{{ route('admin.organization.unit-types.index') }}">Jenis Unit</a>.
              @else
                .
              @endif
            </div>
            <div class="alert alert-info small mt-3 mb-0" aria-live="polite">
              <strong id="orgAddGuidanceTitle">Pilih jenis unit</strong>
              <div id="orgAddGuidance" class="mt-1">Petunjuk pengisian muncul sesuai jenis yang dipilih.</div>
            </div>
          </section>

          {{-- Langkah 3 --}}
          <section class="ow-panel" data-step="3" hidden>
            <h6 class="mb-1">3. Identitas unit</h6>
            <p class="text-secondary small mb-3" id="orgAddContext"></p>
            <div class="row g-3">
              <div class="col-md-7">
                <label for="orgAddName" class="form-label fw-semibold"><span id="orgAddNameLabel">Nama unit</span> <span class="text-danger">*</span></label>
                <input id="orgAddName" name="name" maxlength="160" autocomplete="off" class="form-control">
                <div class="invalid-feedback" data-fb="name"></div>
              </div>
              <div class="col-md-5">
                <label for="orgAddCode" class="form-label fw-semibold">Kode unit <span class="text-danger">*</span></label>
                <input id="orgAddCode" name="code" maxlength="40" autocomplete="off" spellcheck="false" class="form-control text-uppercase">
                <div class="invalid-feedback" data-fb="code"></div>
                <div class="form-text" id="orgAddCodeHint">Unik, huruf/angka dengan tanda hubung.</div>
              </div>
              <div class="col-12">
                <div class="form-label fw-semibold mb-1">Status</div>
                <div class="btn-group" role="group" aria-label="Status unit">
                  <input type="radio" class="btn-check" name="status" id="orgAddStActive" value="active" checked>
                  <label class="btn btn-outline-success" for="orgAddStActive">Aktif</label>
                  <input type="radio" class="btn-check" name="status" id="orgAddStInactive" value="inactive">
                  <label class="btn btn-outline-secondary" for="orgAddStInactive">Nonaktif</label>
                </div>
              </div>
            </div>
          </section>

          {{-- Langkah 4 --}}
          <section class="ow-panel" data-step="4" hidden>
            <h6 class="mb-1">4. Detail tambahan &amp; tinjau</h6>
            <p class="text-secondary small">Alamat dan keterangan opsional. Periksa ringkasan sebelum menyimpan.</p>
            <div class="row g-3">
              <div class="col-12">
                <label for="orgAddAddress" class="form-label">Alamat / lokasi <span class="text-secondary">(opsional)</span></label>
                <input id="orgAddAddress" name="address" maxlength="500" class="form-control">
                <div class="invalid-feedback" data-fb="address"></div>
              </div>
              <div class="col-12">
                <label for="orgAddDescription" class="form-label">Keterangan <span class="text-secondary">(opsional)</span></label>
                <textarea id="orgAddDescription" name="description" rows="2" maxlength="10000" class="form-control"></textarea>
                <div class="d-flex justify-content-between">
                  <div class="invalid-feedback" data-fb="description"></div>
                  <div class="form-text ms-auto"><span id="orgAddDescCount">0</span>/10.000</div>
                </div>
              </div>
            </div>
            <div class="ow-review mt-3" aria-live="polite">
              <div class="fw-semibold mb-2">Ringkasan</div>
              <dl class="row mb-0 small">
                <dt class="col-sm-3">Unit induk</dt><dd class="col-sm-9" id="orgRvParent"></dd>
                <dt class="col-sm-3">Jenis unit</dt><dd class="col-sm-9" id="orgRvType"></dd>
                <dt class="col-sm-3">Nama</dt><dd class="col-sm-9" id="orgRvName"></dd>
                <dt class="col-sm-3">Kode</dt><dd class="col-sm-9" id="orgRvCode"></dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9" id="orgRvStatus"></dd>
              </dl>
            </div>
          </section>
        </div>

        <div class="modal-footer justify-content-between">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary d-none" id="orgAddBack">‹ Kembali</button>
            <button type="button" class="btn btn-primary" id="orgAddNext">Lanjut ›</button>
            <button type="submit" class="btn btn-success d-none" id="orgAddSubmit">Simpan unit</button>
          </div>
        </div>
      </form>
    </div>
  </div>
@endcan
@push('scripts')
<style>
  .ow-steps { list-style: none; display: flex; margin: 0 0 1.25rem; padding: 0; }
  .ow-step { flex: 1; position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; }
  .ow-step::before { content: ""; position: absolute; top: 17px; left: -50%; width: 100%; height: 2px; background: var(--bs-border-color); z-index: 0; }
  .ow-step:first-child::before { display: none; }
  .ow-step.is-done::before, .ow-step.is-active::before { background: var(--bs-primary); }
  .ow-dot { position: relative; z-index: 1; width: 36px; height: 36px; border-radius: 50%; border: 2px solid var(--bs-border-color);
    background: var(--bs-body-bg); color: var(--bs-secondary-color); font-weight: 600; display: grid; place-items: center; padding: 0; }
  .ow-dot:disabled { cursor: not-allowed; opacity: .7; }
  .ow-step.is-active .ow-dot { border-color: var(--bs-primary); color: var(--bs-primary); box-shadow: 0 0 0 .25rem rgba(var(--bs-primary-rgb), .15); }
  .ow-step.is-done .ow-dot { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
  .ow-step.is-done .ow-num { display: none; }
  .ow-step.is-done .ow-dot::after { content: "✓"; }
  .ow-step.is-error .ow-dot { border-color: var(--bs-danger); color: var(--bs-danger); background: var(--bs-body-bg); }
  .ow-step.is-error .ow-dot::after { content: none; }
  .ow-step.is-error .ow-num { display: inline; }
  .ow-label { font-size: .78rem; margin-top: .35rem; color: var(--bs-secondary-color); }
  .ow-step.is-active .ow-label { color: var(--bs-body-color); font-weight: 600; }
  .ow-panel[hidden] { display: none; }
  .ow-review { background: var(--bs-tertiary-bg); border-radius: .6rem; padding: .85rem 1rem; }
  #orgAddModal .invalid-feedback { display: none; }
  #orgAddModal .is-invalid ~ .select2-container--bootstrap-5 .select2-selection { border-color: var(--bs-form-invalid-border-color, #dc3545); }
  @media (max-width: 575.98px) { .ow-label { display: none; } .ow-step.is-active .ow-label { display: block; position: absolute; top: 40px; white-space: nowrap; } .ow-steps { margin-bottom: 1.8rem; } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modalEl = document.getElementById('orgAddModal');
  if (!modalEl) return;
  var $j = window.jQuery;
  var catalog = @json($catalog);
  var choices = @json($parentChoices);
  var rootTypeIds = @json($rootTypeIds);
  var checkUrl = @json(route('admin.organization.units.check-code'));
  var reopen = @json($reopen);
  var noParent = @json(! $hasParentOptions);
  var CODE_RE = /^[A-Za-z0-9][A-Za-z0-9._-]*$/;

  var el = function (id) { return document.getElementById(id); };
  var pickSel = el('orgAddParentPick'), typeSel = el('orgAddType'), parentVal = el('orgAddParentVal');
  var nameEl = el('orgAddName'), codeEl = el('orgAddCode'), addrEl = el('orgAddAddress'), descEl = el('orgAddDescription');
  var panels = Array.prototype.slice.call(modalEl.querySelectorAll('.ow-panel'));
  var stepEls = Array.prototype.slice.call(modalEl.querySelectorAll('.ow-step'));
  var btnBack = el('orgAddBack'), btnNext = el('orgAddNext'), btnSubmit = el('orgAddSubmit'), form = el('orgAddForm');
  var parentOpts = Array.prototype.slice.call(pickSel.options).slice(1), desiredType = null;
  var current = 1, maxReached = 1, done = {}, codeState = { value: '', status: 'idle' }, codeTimer = null;
  var FIELD_STEP = { parent_id: 1, type_id: 2, name: 3, code: 3, status: 3, address: 4, description: 4 };

  function val(e) { return (e.value || '').trim(); }
  function choiceById(id) { return choices.filter(function (c) { return String(c.id) === String(id); })[0] || null; }
  function typeOf(id) { return catalog[id] || null; }
  function isRoot() { return pickSel.value === 'root'; }
  function currentType() { return typeSel.value ? typeOf(typeSel.value) : null; }
  function allowedTypeIds() { if (isRoot()) return rootTypeIds; var c = choiceById(pickSel.value); return c ? c.type_ids : []; }
  function parentLabel() {
    if (isRoot()) return 'Tanpa induk (unit tingkat teratas)';
    var c = choiceById(pickSel.value); return c ? c.path : '';
  }
  function onChange(node, fn) { if ($j) $j(node).on('change', fn); else node.addEventListener('change', fn); }

  // ---------- select2 ----------
  function initSelect2() {
    if (!($j && $j.fn.select2)) return;
    $j(pickSel).select2({
      dropdownParent: $j(modalEl), placeholder: pickSel.getAttribute('data-placeholder'), minimumResultsForSearch: 0,
      matcher: function (params, data) {
        var term = $j.trim(params.term || '').toLowerCase(); if (term === '') return data;
        var ds = (data.element || {}).dataset || {};
        return [data.text, ds.code || '', ds.path || ''].join(' ').toLowerCase().indexOf(term) > -1 ? data : null;
      },
      templateResult: function (s) {
        if (!s.id) return s.text;
        var ds = s.element.dataset, depth = parseInt(ds.depth || '0', 10);
        var $r = $j('<div class="s2-unit"></div>');
        if (depth > 0) $r.addClass('s2-child').css('margin-left', (depth * 14) + 'px').css('padding-left', '.5rem');
        $r.append($j('<div class="s2-unit-name"></div>').text(ds.name || s.text));
        $r.append($j('<div class="s2-unit-meta"></div>').text((ds.code ? ds.code + ' · ' : '') + (ds.path || '')));
        return $r;
      },
      templateSelection: function (s) { return s.element && s.element.dataset && s.element.dataset.name ? s.element.dataset.name : s.text; }
    });
    $j(typeSel).select2({ dropdownParent: $j(modalEl), placeholder: typeSel.getAttribute('data-placeholder'), minimumResultsForSearch: 0 });
  }

  // ---------- induk: bisa disaring menurut jenis yang ingin dibuat (tombol pada alur pengisian) ----------
  function rebuildParents(typeId) {
    while (pickSel.options.length > 1) pickSel.remove(1);
    var n = 0;
    parentOpts.forEach(function (o) {
      var ok = true;
      if (typeId) {
        if (o.value === 'root') ok = rootTypeIds.map(String).indexOf(String(typeId)) > -1;
        else { var c = choiceById(o.value); ok = !!c && c.type_ids.map(String).indexOf(String(typeId)) > -1; }
      }
      if (ok) { pickSel.appendChild(o); n++; }
    });
    pickSel.value = '';
    if ($j) $j(pickSel).trigger('change.select2');
    var note = el('orgAddFilterNote'), t = typeId ? typeOf(typeId) : null;
    note.textContent = t ? 'Menampilkan unit induk yang boleh diberi ' + t.name + '.' : '';
    note.classList.toggle('d-none', !t);
    return n;
  }

  // ---------- jenis menyesuaikan induk ----------
  function rebuildTypes(keep) {
    while (typeSel.options.length > 1) typeSel.remove(1);
    var ids = allowedTypeIds(), n = 0;
    ids.forEach(function (id) {
      var t = typeOf(id); if (!t) return;
      var o = document.createElement('option'); o.value = t.id; o.textContent = t.name;
      if (keep && String(keep) === String(t.id)) o.selected = true;
      typeSel.appendChild(o); n++;
    });
    if (n === 1) typeSel.options[1].selected = true;
    else if (!keep || !typeSel.querySelector('option[value="' + keep + '"]')) typeSel.value = '';
    if ($j) $j(typeSel).trigger('change.select2');
    el('orgAddTypeHelp').textContent = n === 1
      ? 'Hanya satu jenis yang diizinkan di bawah unit induk ini, sudah dipilih otomatis.'
      : 'Hanya jenis yang diizinkan di bawah unit induk yang tampil.';
    applyType();
  }
  function applyType() {
    var t = currentType();
    el('orgAddGuidanceTitle').textContent = t ? t.title : 'Pilih jenis unit';
    el('orgAddGuidance').textContent = t ? t.guidance : 'Petunjuk pengisian muncul sesuai jenis yang dipilih.';
    el('orgAddNameLabel').textContent = t ? t.name_label : 'Nama unit';
    nameEl.placeholder = t ? t.name_placeholder : 'Contoh: Nama unit organisasi';
    codeEl.placeholder = t ? t.code_placeholder : 'Contoh: UNIT-001';
    el('orgAddContext').textContent = (t ? t.name : '') + (pickSel.value ? ' · ' + (isRoot() ? 'tingkat teratas' : 'di bawah ' + (choiceById(pickSel.value) || {}).name) : '');
    el('orgAddSubtitle').textContent = pickSel.value ? 'Di bawah: ' + (isRoot() ? 'tingkat teratas (tanpa induk)' : (choiceById(pickSel.value) || {}).name) : 'Isi berurutan dari langkah 1. Setiap langkah diperiksa sebelum lanjut.';
  }

  // ---------- cek kode unik ----------
  function setCodeHint(text, cls) { var h = el('orgAddCodeHint'); h.className = 'form-text ' + (cls || ''); h.textContent = text; }
  function onCodeInput() {
    var up = codeEl.value.toUpperCase(); if (codeEl.value !== up) codeEl.value = up;
    clearErr('code');
    var v = val(codeEl); clearTimeout(codeTimer);
    if (v === '' || !CODE_RE.test(v)) { codeState = { value: v, status: 'idle' }; setCodeHint('Unik, huruf/angka dengan tanda hubung.'); return; }
    codeState = { value: v, status: 'checking' }; setCodeHint('Memeriksa ketersediaan kode…', 'text-secondary');
    codeTimer = setTimeout(function () {
      fetch(checkUrl + '?code=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (val(codeEl) !== v || typeof j.available !== 'boolean') return;
          codeState = { value: v, status: j.available ? 'ok' : 'taken' };
          if (j.available) setCodeHint('✓ Kode tersedia.', 'text-success'); else setCodeHint('✗ Kode sudah dipakai unit lain.', 'text-danger');
        }).catch(function () { codeState = { value: v, status: 'unknown' }; setCodeHint('Unik, huruf/angka dengan tanda hubung.'); });
    }, 400);
  }

  // ---------- validasi ----------
  var rules = {
    1: function () { if (noParent) return [['parent_id', 'Belum ada unit induk yang tersedia. Buat unit induknya lebih dulu.']]; return pickSel.value ? [] : [['parent_id', 'Pilih unit induk, atau "Tanpa induk" untuk unit tingkat teratas.']]; },
    2: function () {
      if (!typeSel.value) return [['type_id', 'Pilih jenis unit terlebih dahulu.']];
      if (allowedTypeIds().map(String).indexOf(String(typeSel.value)) === -1) return [['type_id', 'Jenis ini tidak diizinkan di bawah unit induk yang dipilih.']];
      return [];
    },
    3: function () {
      var e = [], n = val(nameEl), c = val(codeEl);
      if (!n) e.push(['name', 'Nama unit wajib diisi.']); else if (n.length < 2) e.push(['name', 'Nama unit minimal 2 karakter.']); else if (n.length > 160) e.push(['name', 'Nama unit maksimal 160 karakter.']);
      if (!c) e.push(['code', 'Kode unit wajib diisi.']);
      else if (c.length > 40) e.push(['code', 'Kode unit maksimal 40 karakter.']);
      else if (!CODE_RE.test(c)) e.push(['code', 'Kode hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung (diawali huruf/angka), tanpa spasi.']);
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
  function inputFor(f) { return f === 'parent_id' ? pickSel : (f === 'type_id' ? typeSel : el({ name: 'orgAddName', code: 'orgAddCode', address: 'orgAddAddress', description: 'orgAddDescription' }[f])); }
  function clearErr(f) {
    var i = inputFor(f); if (i) i.classList.remove('is-invalid');
    var fb = modalEl.querySelector('[data-fb="' + f + '"]'); if (fb) { fb.style.display = 'none'; fb.textContent = ''; }
  }
  function showErrs(list) {
    list.forEach(function (it) {
      var i = inputFor(it[0]); if (i) i.classList.add('is-invalid');
      var fb = modalEl.querySelector('[data-fb="' + it[0] + '"]'); if (fb) { fb.textContent = it[1]; fb.style.display = 'block'; }
    });
  }
  function validateStep(n, show) {
    var errs = rules[n]();
    if (show) {
      Object.keys(FIELD_STEP).forEach(function (f) { if (FIELD_STEP[f] === n) clearErr(f); });
      showErrs(errs);
    }
    stepEls[n - 1].classList.toggle('is-error', !!show && errs.length > 0);
    if (show && errs.length && n >= 3) { var first = inputFor(errs[0][0]); if (first && first.focus) first.focus(); }
    return errs.length === 0;
  }

  // ---------- navigasi ----------
  function fillReview() {
    var t = currentType();
    el('orgRvParent').textContent = parentLabel() || '—';
    el('orgRvType').textContent = t ? t.name : '—';
    el('orgRvName').textContent = val(nameEl) || '—';
    el('orgRvCode').textContent = val(codeEl) || '—';
    el('orgRvStatus').textContent = el('orgAddStInactive').checked ? 'Nonaktif' : 'Aktif';
  }
  function go(n) {
    current = n;
    panels.forEach(function (p) { p.hidden = Number(p.dataset.step) !== n; });
    stepEls.forEach(function (s, i) {
      var k = i + 1;
      s.classList.toggle('is-active', k === n);
      s.classList.toggle('is-done', !!done[k] && k !== n);
      var dot = s.querySelector('.ow-dot'); dot.disabled = k > maxReached;
      if (k === n) dot.setAttribute('aria-current', 'step'); else dot.removeAttribute('aria-current');
    });
    btnBack.classList.toggle('d-none', n === 1);
    btnNext.classList.toggle('d-none', n === 4);
    btnNext.disabled = (n === 1 && noParent);
    btnSubmit.classList.toggle('d-none', n !== 4);
    if (n === 3) { applyType(); nameEl.focus(); }
    if (n === 4) { fillReview(); addrEl.focus(); }
  }
  btnNext.addEventListener('click', function () {
    if (!validateStep(current, true)) return;
    done[current] = true; var n = Math.min(current + 1, 4); maxReached = Math.max(maxReached, n); go(n);
  });
  btnBack.addEventListener('click', function () { go(Math.max(current - 1, 1)); });
  stepEls.forEach(function (s) {
    s.querySelector('.ow-dot').addEventListener('click', function () {
      var target = Number(this.dataset.goto); if (target === current) return;
      if (target > current) { for (var k = current; k < target; k++) { if (!validateStep(k, true)) { go(k); return; } done[k] = true; } }
      go(target);
    });
  });
  // Enter di kolom isian = lanjut, bukan kirim sebelum langkah terakhir
  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || current === 4) return;
    e.preventDefault(); btnNext.click();
  });
  form.addEventListener('submit', function (e) {
    for (var k = 1; k <= 4; k++) { if (!validateStep(k, true)) { e.preventDefault(); go(k); return; } }
    parentVal.value = isRoot() ? '' : pickSel.value;
    btnSubmit.disabled = true; btnSubmit.textContent = 'Menyimpan…';
  });

  // ---------- peristiwa ----------
  function afterPickChange() {
    clearErr('parent_id'); parentVal.value = isRoot() ? '' : pickSel.value;
    rebuildTypes(desiredType); maxReached = 1; done = {}; stepEls.forEach(function (s) { s.classList.remove('is-done', 'is-error'); });
    if (pickSel.value) { done[1] = true; maxReached = 2; }
    go(1);
  }
  onChange(pickSel, afterPickChange);
  var rootBtn = el('orgAddPickRoot');
  if (rootBtn) rootBtn.addEventListener('click', function () { setPick('root'); if (pickSel.value === 'root') { afterPickChange(); btnNext.click(); } });
  onChange(typeSel, function () { clearErr('type_id'); applyType(); });
  nameEl.addEventListener('input', function () { clearErr('name'); });
  codeEl.addEventListener('input', onCodeInput);
  addrEl.addEventListener('input', function () { clearErr('address'); });
  descEl.addEventListener('input', function () { clearErr('description'); el('orgAddDescCount').textContent = descEl.value.length.toLocaleString('id-ID'); });

  function setPick(v) { pickSel.value = v; if ($j) $j(pickSel).trigger('change.select2'); }
  function resetAll() {
    ['orgAddName', 'orgAddCode', 'orgAddAddress', 'orgAddDescription'].forEach(function (id) { el(id).value = ''; });
    el('orgAddStActive').checked = true; el('orgAddDescCount').textContent = '0';
    Object.keys(FIELD_STEP).forEach(clearErr);
    el('orgAddServerErrors').classList.add('d-none');
    codeState = { value: '', status: 'idle' }; setCodeHint('Unik, huruf/angka dengan tanda hubung.');
    done = {}; maxReached = 1; stepEls.forEach(function (s) { s.classList.remove('is-done', 'is-error'); });
    btnSubmit.disabled = false; btnSubmit.textContent = 'Simpan unit';
    setPick(''); parentVal.value = ''; rebuildTypes(null);
  }

  // dibuka dari tombol: "Tambah unit" (tanpa induk terpilih) atau "+ Unit" pada bagan (induk sudah pasti)
  modalEl.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget; if (!btn) return;
    resetAll();
    desiredType = btn.dataset.typeId ? String(btn.dataset.typeId) : null;
    var count = rebuildParents(desiredType);
    if (btn.dataset.parentId !== undefined) {
      var pid = btn.dataset.parentId === '' ? 'root' : btn.dataset.parentId;
      setPick(pid);
      if (pickSel.value === pid) { parentVal.value = pid === 'root' ? '' : pid; rebuildTypes(desiredType); done[1] = true; maxReached = 2; go(2); return; }
    } else if (desiredType && count === 1) {
      // hanya satu induk yang cocok: langsung ke pilihan jenis
      setPick(pickSel.options[1].value);
      parentVal.value = isRoot() ? '' : pickSel.value; rebuildTypes(desiredType); done[1] = true; maxReached = 2; go(2); return;
    }
    go(1);
  });

  initSelect2();
  resetAll();
  go(1);

  // dibuka ulang setelah galat validasi dari server: isian dipertahankan, lompat ke langkah bermasalah
  if (reopen) {
    setPick(reopen.parent); parentVal.value = reopen.parent === 'root' ? '' : reopen.parent;
    rebuildTypes(reopen.type_id);
    nameEl.value = reopen.name || ''; codeEl.value = (reopen.code || '').toUpperCase();
    addrEl.value = reopen.address || ''; descEl.value = reopen.description || '';
    el(reopen.status === 'inactive' ? 'orgAddStInactive' : 'orgAddStActive').checked = true;
    el('orgAddDescCount').textContent = descEl.value.length.toLocaleString('id-ID');
    if (val(codeEl)) onCodeInput();
    var list = [], first = 5, msgs = el('orgAddServerList');
    Object.keys(reopen.errors || {}).forEach(function (f) {
      (reopen.errors[f] || []).forEach(function (m) { var li = document.createElement('li'); li.textContent = m; msgs.appendChild(li); });
      if (FIELD_STEP[f]) { first = Math.min(first, FIELD_STEP[f]); showErrs([[f, reopen.errors[f][0]]]); }
    });
    if (msgs.children.length) el('orgAddServerErrors').classList.remove('d-none');
    var start = first === 5 ? 1 : first;
    for (var s = 1; s < start; s++) done[s] = true;
    maxReached = 4; stepEls[start - 1].classList.add('is-error'); go(start);
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
});
</script>
@endpush
