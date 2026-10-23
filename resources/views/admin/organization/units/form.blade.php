@extends('admin._layout')
@php($isEdit = $unit->exists)
@section('title', $isEdit ? 'Ubah Unit Organisasi' : 'Tambah Unit Organisasi')
@section('parent', 'Unit Organisasi')
@section('parent_url', route('admin.organization.units.index'))
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'units', 'compact' => true])</div>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 px-4">
      <h5 class="mb-1">{{ $isEdit ? 'Ubah data unit' : 'Buat unit berdasarkan jenisnya' }}</h5>
      <p class="text-muted mb-0 small">Pilih jenis unit terlebih dahulu. Formulir dan pilihan unit induk akan menyesuaikan jenis tersebut.</p>
    </div>
    <div class="card-body p-4">
      <form method="POST" action="{{ $isEdit ? route('admin.organization.units.update', $unit) : route('admin.organization.units.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-3">
          <div class="col-12">
            <label for="type_id" class="form-label fw-semibold">Jenis unit organisasi <span class="text-danger">*</span></label>
            <select id="type_id" name="type_id" required class="form-select form-select-lg @error('type_id') is-invalid @enderror">
              <option value="">Pilih jenis unit terlebih dahulu</option>
              @foreach($types as $type)
                <option value="{{ $type->id }}" data-slug="{{ $type->slug }}" data-name="{{ $type->name }}" @selected(old('type_id', $unit->type_id) == $type->id)>{{ $type->name }}</option>
              @endforeach
            </select>
            @error('type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-12" id="type-guidance" aria-live="polite">
            <div class="alert alert-info mb-0"><strong id="guidance-title">Pilih jenis unit</strong><div id="guidance-text" class="small mt-1">Petunjuk pengisian akan muncul sesuai jenis unit yang dipilih.</div></div>
          </div>

          <div class="col-md-8">
            <label for="name" class="form-label" id="name-label">Nama unit <span class="text-danger">*</span></label>
            <input id="name" name="name" value="{{ old('name', $unit->name) }}" required maxlength="160" placeholder="Contoh: Nama unit organisasi" class="form-control @error('name') is-invalid @enderror">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="code" class="form-label">Kode unit <span class="text-danger">*</span></label>
            <input id="code" name="code" value="{{ old('code', $unit->code) }}" required maxlength="40" placeholder="Contoh: KOMWIL-SULBAR" class="form-control @error('code') is-invalid @enderror">
            <div class="form-text">Kode harus unik dan digunakan sebagai identitas unit.</div>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-8" id="parent-wrap">
            <label for="parent_id" class="form-label" id="parent-label">Unit induk <span class="text-danger">*</span></label>
            <select id="parent_id" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
              <option value="">Pilih unit induk</option>
              @foreach($parents as $parent)
                <option value="{{ $parent->id }}" data-parent-type="{{ $parent->type?->slug }}" @selected(old('parent_id', $unit->parent_id) == $parent->id)>
                  {{ $parent->path }} ({{ $parent->code }})
                </option>
              @endforeach
            </select>
            <div class="form-text" id="parent-help">Pilihan unit induk akan disesuaikan dengan jenis unit.</div>
            @error('parent_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" required class="form-select">
              <option value="active" @selected(old('status', $unit->status ?: 'active') === 'active')>Aktif</option>
              <option value="inactive" @selected(old('status', $unit->status) === 'inactive')>Nonaktif</option>
            </select>
          </div>

          <div class="col-12"><hr class="my-1"><h6 class="mb-0">Informasi tambahan <span class="text-muted fw-normal small">(opsional)</span></h6></div>
          <div class="col-12" id="specific-fields">
            <div class="row g-3">
              <div class="col-12">
                <label for="address" class="form-label">Alamat / lokasi kantor</label>
                <input id="address" name="address" value="{{ old('address', $unit->address) }}" maxlength="500" class="form-control @error('address') is-invalid @enderror" placeholder="Alamat kantor atau lokasi unit">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-12">
                <label for="description" class="form-label">Keterangan</label>
                <textarea id="description" name="description" rows="3" maxlength="10000" class="form-control @error('description') is-invalid @enderror" placeholder="Tugas, cakupan, atau keterangan unit">{{ old('description', $unit->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
          @if($isEdit)
            <a href="{{ route('admin.organization.units.show', $unit) }}" class="btn btn-light">Kembali ke detail</a>
          @else
            <a href="{{ route('admin.organization.units.index') }}" class="btn btn-light">Batal</a>
          @endif
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>{{ $isEdit ? 'Simpan perubahan' : 'Simpan unit organisasi' }}</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const typeSelect = document.getElementById('type_id');
      const parentSelect = document.getElementById('parent_id');
      const parentWrap = document.getElementById('parent-wrap');
      const parentHelp = document.getElementById('parent-help');
      const guidanceTitle = document.getElementById('guidance-title');
      const guidanceText = document.getElementById('guidance-text');
      const nameLabel = document.getElementById('name-label');
      const nameInput = document.getElementById('name');
      const codeInput = document.getElementById('code');
      const rootAllowed = @json($canCreateRoot || ($isEdit && $unit->parent_id === null));
      // Aturan hierarki berasal dari config/organization.php (sama dengan validasi server).
      const levels = @json($levels);

      function updateForm() {
        const option = typeSelect.options[typeSelect.selectedIndex];
        const level = option ? levels[option.dataset.slug] : null;
        const allowedParents = level ? level.parents : null; // null = jenis bebas, [] = tingkat teratas
        let allowedCount = 0;

        Array.from(parentSelect.options).forEach(function (o, index) {
          if (index === 0) return;
          const ok = !allowedParents || allowedParents.length === 0 || allowedParents.indexOf(o.dataset.parentType) !== -1;
          o.hidden = !ok;
          o.disabled = !ok;
          if (ok) allowedCount++;
        });
        if (parentSelect.selectedOptions[0] && parentSelect.selectedOptions[0].disabled) parentSelect.value = '';

        if (!level) {
          guidanceTitle.textContent = option && option.value ? option.dataset.name : 'Pilih jenis unit';
          guidanceText.textContent = option && option.value
            ? 'Jenis unit ini tidak memiliki aturan khusus. Pilih unit induk bila ada.'
            : 'Petunjuk pengisian akan muncul sesuai jenis unit yang dipilih.';
          nameLabel.textContent = 'Nama unit *';
          nameInput.placeholder = 'Contoh: Nama unit organisasi';
          codeInput.placeholder = 'Contoh: UNIT-001';
          parentWrap.classList.remove('d-none');
          parentSelect.disabled = false;
          parentSelect.required = !!(option && option.value) && !rootAllowed;
          parentHelp.textContent = 'Pilih unit induk bila unit ini berada di bawah unit lain.';
          return;
        }

        guidanceTitle.textContent = level.title;
        guidanceText.textContent = level.guidance;
        nameLabel.textContent = level.name_label + ' *';
        nameInput.placeholder = level.name_placeholder;
        codeInput.placeholder = level.code_placeholder;

        if (allowedParents.length === 0) {
          parentWrap.classList.add('d-none');
          parentSelect.required = false;
          parentSelect.disabled = true; // tidak terkirim: induk dikosongkan
          parentSelect.value = '';
        } else {
          parentWrap.classList.remove('d-none');
          parentSelect.disabled = false;
          parentSelect.required = true;
          parentHelp.textContent = allowedCount === 0
            ? 'Belum ada unit induk yang sesuai dan valid. Buat unit induknya terlebih dahulu.'
            : 'Hanya unit induk yang sesuai aturan yang ditampilkan.';
        }
      }

      typeSelect.addEventListener('change', function () {
        parentSelect.value = ''; // hindari mempertahankan induk yang tidak cocok
        updateForm();
      });
      updateForm();
    });
  </script>
@endsection
