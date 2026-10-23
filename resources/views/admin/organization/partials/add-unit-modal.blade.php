  @php
    $reopen = null;
    if (old('from_modal')) {
        $oldParent = old('parent_id') !== null && old('parent_id') !== '' ? (int) old('parent_id') : null;
        $reopen = [
            'parent_id' => $oldParent,
            'parent_name' => $oldParent ? ($unitNames[$oldParent] ?? '') : '',
            'types' => $oldParent ? ($addable[$oldParent] ?? []) : $rootTypes,
            'type_id' => old('type_id'),
        ];
    }
  @endphp
  @if ($addable || $rootTypes)
    <div class="modal fade" id="orgAddModal" tabindex="-1" aria-labelledby="orgAddTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('admin.organization.units.store') }}" class="modal-content" id="orgAddForm">
          @csrf
          <input type="hidden" name="from_modal" value="1">
          <input type="hidden" name="return_to" value="organization">
          <input type="hidden" name="status" value="active">
          <input type="hidden" name="parent_id" id="orgAddParent" value="{{ old('parent_id') }}">
          <div class="modal-header">
            <div>
              <h5 class="modal-title" id="orgAddTitle">Tambah unit</h5>
              <div class="small text-secondary" id="orgAddSubtitle"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            @if (old('from_modal') && $errors->any())
              <div class="alert alert-danger small" role="alert">
                <ul class="mb-0 ps-3">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
              </div>
            @endif

            <div class="mb-3" id="orgAddTypeWrap">
              <label for="orgAddType" class="form-label fw-semibold">Jenis unit</label>
              <select id="orgAddType" name="type_id" class="form-select" required></select>
            </div>
            <div class="alert alert-info small py-2" id="orgAddGuidance"></div>

            <div class="mb-3">
              <label for="orgAddName" class="form-label" id="orgAddNameLabel">Nama unit</label>
              <input id="orgAddName" name="name" value="{{ old('from_modal') ? old('name') : '' }}" required maxlength="160" class="form-control">
            </div>
            <div class="mb-3">
              <label for="orgAddCode" class="form-label">Kode unit</label>
              <input id="orgAddCode" name="code" value="{{ old('from_modal') ? old('code') : '' }}" required maxlength="40" class="form-control">
              <div class="form-text">Kode harus unik dan menjadi identitas unit.</div>
            </div>
            <div class="mb-3">
              <label for="orgAddAddress" class="form-label">Alamat / lokasi <span class="text-secondary">(opsional)</span></label>
              <input id="orgAddAddress" name="address" value="{{ old('from_modal') ? old('address') : '' }}" maxlength="500" class="form-control">
            </div>
            <div>
              <label for="orgAddDescription" class="form-label">Keterangan <span class="text-secondary">(opsional)</span></label>
              <textarea id="orgAddDescription" name="description" rows="2" maxlength="10000" class="form-control">{{ old('from_modal') ? old('description') : '' }}</textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan unit</button>
          </div>
        </form>
      </div>
    </div>
  @endif
@push('scripts')
<script>
(function () {
  var modalEl = document.getElementById('orgAddModal');
  if (!modalEl) return;
  var $ = function (id) { return document.getElementById(id); };
  var typeSelect = $('orgAddType'), typeWrap = $('orgAddTypeWrap');
  var types = [];

  function applyType() {
    var t = types.filter(function (x) { return String(x.id) === typeSelect.value; })[0] || types[0];
    if (!t) return;
    $('orgAddTitle').textContent = 'Tambah ' + t.title;
    $('orgAddGuidance').textContent = t.guidance;
    $('orgAddNameLabel').textContent = t.name_label;
    $('orgAddName').placeholder = t.name_placeholder;
    $('orgAddCode').placeholder = t.code_placeholder;
  }

  function fill(parentId, parentName, list, selectedTypeId) {
    types = list;
    $('orgAddParent').value = parentId || '';
    $('orgAddSubtitle').textContent = parentName ? 'Di bawah: ' + parentName : 'Unit tingkat teratas';
    typeSelect.innerHTML = '';
    list.forEach(function (t) {
      var o = document.createElement('option');
      o.value = t.id; o.textContent = t.name;
      if (selectedTypeId && String(selectedTypeId) === String(t.id)) o.selected = true;
      typeSelect.appendChild(o);
    });
    // satu pilihan saja: jenis sudah pasti, tidak perlu dropdown
    typeWrap.classList.toggle('d-none', list.length === 1);
    applyType();
  }

  typeSelect.addEventListener('change', applyType);

  modalEl.addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    if (!btn) return; // dibuka ulang setelah galat validasi: isian dipertahankan
    fill(btn.dataset.parentId, btn.dataset.parentName, JSON.parse(btn.dataset.types || '[]'));
    ['orgAddName', 'orgAddCode', 'orgAddAddress', 'orgAddDescription'].forEach(function (id) { $(id).value = ''; });
    var alertBox = modalEl.querySelector('.alert-danger');
    if (alertBox) alertBox.remove();
  });
  modalEl.addEventListener('shown.bs.modal', function () { $('orgAddName').focus(); });

  var reopen = @json($reopen);
  if (reopen) {
    fill(reopen.parent_id, reopen.parent_name, reopen.types, reopen.type_id);
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
})();
</script>
@endpush
