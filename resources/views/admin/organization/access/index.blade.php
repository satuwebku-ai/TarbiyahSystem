@extends('admin._layout')
@section('title', 'Akses Unit Organisasi')
@section('content')
  @include('partials.admin.select2-assets')

  {{-- Penjelasan fungsi halaman --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body py-3">
      <button class="btn btn-link text-decoration-none p-0 fw-semibold d-flex align-items-center gap-2" type="button"
              data-bs-toggle="collapse" data-bs-target="#accessHelp" aria-expanded="true" aria-controls="accessHelp">
        <x-icon name="help" size="18"/> Apa fungsi halaman ini?
      </button>
      <div class="collapse show" id="accessHelp">
        <div class="row g-3 mt-1">
          <div class="col-lg-5">
            <p class="small mb-2">Halaman ini menentukan <strong>data unit mana yang boleh dilihat dan dikelola</strong> oleh setiap akun pengelola. Tanpa pembatasan, semua pengelola bisa melihat seluruh yayasan.</p>
            <p class="small text-secondary mb-0">Contoh: akun <em>Komisaris Wilayah Sulawesi Barat</em> cukup diberi unit <strong>KOMWIL Sulawesi Barat</strong>. Ia otomatis hanya melihat KOMWIL tersebut beserta semua KOMDA dan sekolah di bawahnya, tanpa melihat wilayah lain.</p>
          </div>
          <div class="col-lg-7">
            <ul class="small mb-0 ps-3 d-flex flex-column gap-1">
              <li><strong>Pilih unit induk</strong> sudah cukup. Semua unit turunannya ikut otomatis, termasuk unit yang dibuat kemudian.</li>
              <li>Berlaku untuk daftar unit, <strong>data pegawai, penugasan, program kerja, absensi, dan laporan</strong>. Pemeriksaan dilakukan di server, jadi tidak bisa dilewati lewat alamat langsung.</li>
              <li>Akun <strong>tanpa unit terpilih tidak dapat melihat data organisasi apa pun</strong>.</li>
              <li><strong>Super Admin</strong> dan akun dengan izin <em>"Akses semua unit organisasi"</em> selalu punya akses penuh, sehingga pilihan di sini tidak berpengaruh untuk mereka.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Pilih akun --}}
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.organization.access.index') }}" class="row g-3 align-items-end">
        <div class="col-lg-6">
          <label for="admin_id" class="form-label small mb-1">Akun pengelola</label>
          <select id="admin_id" name="admin_id" class="form-select js-select2" onchange="this.form.submit()">
            @foreach($admins as $admin)
              <option value="{{ $admin->id }}" @selected($selected?->id === $admin->id)>{{ $admin->name }} · {{ $admin->username }}</option>
            @endforeach
          </select>
        </div>
        @if($selected)
          <div class="col-lg-6">
            <div class="small text-secondary mb-1">Role & status</div>
            <div class="d-flex flex-wrap gap-1">
              @forelse($selected->roles as $role)
                <span class="badge badge-soft-primary">{{ $role->label }}</span>
              @empty
                <span class="badge badge-soft-secondary">Belum memiliki role</span>
              @endforelse
              <span class="badge {{ $selected->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $selected->is_active ? 'Akun aktif' : 'Akun nonaktif' }}</span>
              @if($unrestricted)<span class="badge badge-soft-warning">Akses penuh</span>@endif
            </div>
          </div>
        @endif
      </form>
    </div>
  </div>

  @if(!$selected)
    <div class="card border-0 shadow-sm"><div class="empty-state py-5">Belum ada akun pengelola untuk diberi cakupan unit.</div></div>
  @else
    @if($unrestricted)
      <div class="alert alert-warning mb-0 small">
        <strong>{{ $selected->name }}</strong> memiliki akses penuh ke seluruh unit (Super Admin atau izin akses semua unit), sehingga pembatasan unit tidak berlaku dan daftar di bawah hanya untuk dilihat.
      </div>
    @endif

    <form method="POST" action="{{ route('admin.organization.access.update') }}" id="accessForm"
          data-descendants='@json($descendants)'>
      @csrf @method('PUT')
      <input type="hidden" name="admin_id" value="{{ $selected->id }}">
      <input type="hidden" name="unit_ids[]" value="">
      <fieldset @disabled($unrestricted) class="border-0 p-0 m-0">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-transparent">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div>
                <div class="fw-semibold">Unit yang dapat diakses {{ $selected->name }}</div>
                <div class="small text-secondary">Centang unit induk; turunannya ikut otomatis.</div>
              </div>
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge badge-soft-primary" id="accessDirect">0 unit dipilih</span>
                <span class="badge badge-soft-success" id="accessTotal">0 unit dapat diakses</span>
              </div>
            </div>
            <div class="row g-2 mt-2">
              <div class="col-md-6 col-lg-5">
                <input type="search" id="accessSearch" class="form-control form-control-sm" placeholder="Cari nama atau kode unit…" autocomplete="off">
              </div>
              <div class="col-md-auto d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="accessClear">Kosongkan</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="accessOnlyChecked" aria-pressed="false">Hanya yang dipilih</button>
              </div>
            </div>
            <div class="alert alert-warning small py-2 mt-3 mb-0 d-none" id="accessEmptyWarn">
              Belum ada unit dipilih. Akun ini <strong>tidak akan dapat melihat data organisasi apa pun</strong> sampai diberi unit.
            </div>
          </div>

          <div class="card-body p-2 p-md-3">
            <ul class="access-tree list-unstyled m-0" id="accessList">
              @forelse($units as $unit)
                <li class="access-row" data-id="{{ $unit->id }}" data-depth="{{ $unit->tree_depth }}"
                    data-search="{{ mb_strtolower($unit->name.' '.$unit->code.' '.$unit->type?->name.' '.$unit->tree_path) }}">
                  <label class="access-label" style="padding-left:calc(.75rem + {{ $unit->tree_depth }} * 1.5rem)">
                    @if($unit->tree_depth > 0)<span class="access-branch" aria-hidden="true"></span>@endif
                    <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" @checked(in_array($unit->id, $unitIds, true))>
                    <span class="flex-grow-1 min-w-0">
                      <span class="fw-semibold d-block text-truncate">{{ $unit->name }}</span>
                      <span class="small text-secondary d-block text-truncate">{{ $unit->type?->name }} · <code>{{ $unit->code }}</code>@if($unit->tree_path) · di bawah {{ \Illuminate\Support\Str::afterLast($unit->tree_path, ' › ') }}@endif</span>
                    </span>
                    <span class="badge badge-soft-success access-inherit d-none" data-inherit></span>
                    @if($unit->status !== 'active')<span class="badge badge-soft-secondary">Nonaktif</span>@endif
                  </label>
                </li>
              @empty
                <li class="empty-state py-4">Buat unit organisasi terlebih dahulu.</li>
              @endforelse
            </ul>
            <div class="empty-state py-4 d-none" id="accessNoResult">Tidak ada unit yang cocok dengan pencarian.</div>
          </div>

          <div class="card-footer bg-body d-flex flex-wrap justify-content-between align-items-center gap-2 access-footer">
            <span class="small text-secondary">Perubahan baru berlaku setelah disimpan.</span>
            <div class="d-flex gap-2">
              <a href="{{ route('admin.organization.index') }}" class="btn btn-light">Batal</a>
              <button type="submit" class="btn btn-primary">Simpan cakupan</button>
            </div>
          </div>
        </div>
      </fieldset>
    </form>
  @endif
  @push('scripts')
    @include('partials.admin.clean-url')
  @endpush
@endsection

@push('scripts')
<style>
  .access-row { border-radius:.6rem; }
  .access-row:hover { background:rgba(128,128,128,.07); }
  .access-row.is-checked { background:rgba(var(--bs-primary-rgb),.07); }
  .access-row.is-covered { background:rgba(var(--bs-success-rgb),.06); }
  .access-label { position:relative; display:flex; align-items:center; gap:.75rem; padding:.55rem .75rem; margin:0; cursor:pointer; }
  .access-branch { position:absolute; left:calc(.75rem + var(--d, 0) * 1.5rem - 1rem); top:0; bottom:50%; width:.75rem; border-left:1px solid rgba(128,128,128,.4); border-bottom:1px solid rgba(128,128,128,.4); border-bottom-left-radius:6px; pointer-events:none; }
  .access-footer { position:sticky; bottom:0; z-index:5; border-top:1px solid rgba(128,128,128,.2); }
  fieldset:disabled .access-label { cursor:default; opacity:.75; }
  @media (max-width:575.98px) { .access-label { gap:.5rem; } }
</style>
<script>
(function () {
  var form = document.getElementById('accessForm');
  if (!form) return;
  var desc = JSON.parse(form.dataset.descendants || '{}');
  var rows = Array.prototype.slice.call(form.querySelectorAll('.access-row'));
  var byId = {}; rows.forEach(function (r) { byId[r.dataset.id] = r; });
  var names = {}; rows.forEach(function (r) { names[r.dataset.id] = r.querySelector('.fw-semibold').textContent.trim(); });
  var $ = function (id) { return document.getElementById(id); };
  var onlyChecked = false;

  rows.forEach(function (r) { r.querySelector('.access-branch') && r.querySelector('.access-branch').style.setProperty('--d', r.dataset.depth); });

  function box(r) { return r.querySelector('input[type=checkbox]'); }

  function refresh() {
    var covered = {}, direct = 0, total = {};
    rows.forEach(function (r) {
      if (!box(r).checked) return;
      direct++; total[r.dataset.id] = true;
      (desc[r.dataset.id] || []).forEach(function (id) { total[id] = true; covered[id] = covered[id] || names[r.dataset.id]; });
    });
    rows.forEach(function (r) {
      var checked = box(r).checked, by = covered[r.dataset.id], badge = r.querySelector('[data-inherit]');
      r.classList.toggle('is-checked', checked);
      r.classList.toggle('is-covered', !checked && !!by);
      if (!checked && by) { badge.textContent = 'Ikut ' + by; badge.classList.remove('d-none'); }
      else if (checked && by) { badge.textContent = 'Sudah tercakup ' + by; badge.classList.remove('d-none'); }
      else badge.classList.add('d-none');
    });
    var n = Object.keys(total).length;
    $('accessDirect').textContent = direct + ' unit dipilih';
    $('accessTotal').textContent = n + ' unit dapat diakses';
    $('accessEmptyWarn').classList.toggle('d-none', direct > 0 || form.querySelector('fieldset').disabled);
    filter();
  }

  function filter() {
    var q = $('accessSearch').value.trim().toLowerCase(), shown = 0;
    rows.forEach(function (r) {
      var ok = (!q || r.dataset.search.indexOf(q) > -1) && (!onlyChecked || box(r).checked);
      r.classList.toggle('d-none', !ok);
      if (ok) shown++;
    });
    $('accessNoResult').classList.toggle('d-none', shown > 0 || rows.length === 0);
  }

  form.addEventListener('change', function (e) { if (e.target.matches('input[type=checkbox]')) refresh(); });
  $('accessSearch').addEventListener('input', filter);
  $('accessClear').addEventListener('click', function () { rows.forEach(function (r) { box(r).checked = false; }); refresh(); });
  $('accessOnlyChecked').addEventListener('click', function () {
    onlyChecked = !onlyChecked;
    this.setAttribute('aria-pressed', onlyChecked);
    this.classList.toggle('active', onlyChecked);
    filter();
  });
  refresh();
})();
</script>
@endpush
