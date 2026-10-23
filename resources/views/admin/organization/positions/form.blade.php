@extends('admin._layout')
@php($isEdit = $position->exists)
@section('title', $isEdit ? 'Ubah Jabatan' : 'Tambah Jabatan')
@section('parent', 'Jabatan Organisasi')
@section('parent_url', route('admin.organization.positions.index'))
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'positions', 'compact' => true])</div>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      @if($units->isEmpty())
        <div class="alert alert-info mb-0">Buat unit organisasi yang aktif terlebih dahulu sebelum menambahkan jabatan.</div>
      @else
        <form method="POST" action="{{ $isEdit ? route('admin.organization.positions.update', $position) : route('admin.organization.positions.store') }}">
          @csrf
          @if($isEdit) @method('PUT') @endif
          <div class="row g-3">
            <div class="col-md-6">
              <label for="name" class="form-label">Nama jabatan</label>
              <input id="name" name="name" value="{{ old('name', $position->name) }}" required maxlength="120" class="form-control @error('name') is-invalid @enderror">
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label for="organizational_unit_id" class="form-label">Unit organisasi</label>
              <input type="search" id="unit-filter" class="form-control form-control-sm mb-1" placeholder="Cari unit… (nama, kode, jenis)" autocomplete="off">
              <select id="organizational_unit_id" name="organizational_unit_id" required size="1" class="form-select @error('organizational_unit_id') is-invalid @enderror">
                <option value="">Pilih unit</option>
                @foreach($unitOptions as $opt)
                  <option value="{{ $opt['id'] }}" data-search="{{ mb_strtolower($opt['path'].' '.$opt['code'].' '.$opt['type']) }}" title="{{ $opt['path'] }}" @selected(old('organizational_unit_id', $position->organizational_unit_id) == $opt['id'])>{{ $opt['label'] }}{{ $opt['type'] ? ' ('.$opt['type'].')' : '' }}</option>
                @endforeach
              </select>
              <div class="form-text">Hierarki ditampilkan berjenjang. Ketik di kolom cari untuk menyaring.</div>
              @error('organizational_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              @php($capValue = old('capacity', $position->exists ? $position->capacity : 1))
              @php($unlimited = old('unlimited', $position->exists && $position->capacity === null))
              <label for="capacity" class="form-label">Jumlah pemegang jabatan</label>
              <input id="capacity" type="number" min="1" max="1000" name="capacity" value="{{ $capValue }}" class="form-control @error('capacity') is-invalid @enderror">
              <input type="hidden" name="unlimited" value="0">
              <div class="form-check mt-1">
                <input id="unlimited" class="form-check-input" type="checkbox" name="unlimited" value="1" @checked($unlimited)>
                <label for="unlimited" class="form-check-label">Tanpa batas (beberapa pegawai, misal Staf Kantor)</label>
              </div>
              <div class="form-text">1 = satu orang (Ketua, Sekretaris). Satu pegawai tetap hanya boleh memegang satu jabatan.</div>
              @error('capacity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
              <label for="description" class="form-label">Keterangan</label>
              <textarea id="description" name="description" rows="3" maxlength="500" class="form-control @error('description') is-invalid @enderror">{{ old('description', $position->description) }}</textarea>
              @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
              <input type="hidden" name="is_active" value="0">
              <div class="form-check">
                <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $position->exists ? $position->is_active : true))>
                <label for="is_active" class="form-check-label">Jabatan aktif</label>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('admin.organization.positions.index') }}" class="btn btn-light">Batal</a>
            @unless($isEdit)
              <button type="submit" name="then" value="another" class="btn btn-outline-primary">Simpan &amp; tambah jabatan lain</button>
            @endunless
            <button type="submit" class="btn btn-primary">Simpan jabatan</button>
          </div>
        </form>
      @endif
    </div>
  </div>
@endsection
@push('scripts')
<script>
  (() => {
    const filter = document.getElementById('unit-filter');
    const select = document.getElementById('organizational_unit_id');
    const unlimited = document.getElementById('unlimited');
    const capacity = document.getElementById('capacity');
    if (filter && select) {
      filter.addEventListener('input', () => {
        const q = filter.value.trim().toLowerCase();
        Array.from(select.options).forEach(o => {
          if (o.value === '') return;
          const hit = q === '' || (o.dataset.search || '').includes(q) || o.selected;
          o.hidden = !hit; o.disabled = !hit;
        });
      });
    }
    if (unlimited && capacity) {
      const sync = () => { capacity.disabled = unlimited.checked; };
      unlimited.addEventListener('change', sync); sync();
    }
  })();
</script>
@endpush
