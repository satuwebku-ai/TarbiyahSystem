@extends('admin._layout')
@php $isEdit = $role->exists; $isSuper = $role->name === 'super-admin'; @endphp
@section('title', $isEdit ? 'Ubah Role' : 'Tambah Role')
@section('parent', 'Role & Permission')
@section('parent_url', route('admin.roles.index'))
@section('content')
  <form method="POST" action="{{ $isEdit ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="card border-0 shadow-sm">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="card-body">
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label">Nama tampilan</label>
          <input name="label" value="{{ old('label', $role->label) }}" class="form-control @error('label') is-invalid @enderror" required>
          @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Kode (slug)</label>
          <input name="name" value="{{ old('name', $role->name) }}" placeholder="mis. keuangan" @readonly($isEdit)
                 class="form-control @error('name') is-invalid @enderror" required>
          @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Deskripsi</label>
          <input name="description" value="{{ old('description', $role->description) }}" class="form-control">
        </div>
      </div>

      <h6 class="mb-3">Permission</h6>
      @if($isSuper)
        <div class="alert alert-info">Super Admin otomatis memiliki seluruh akses; daftar ini tidak dapat diubah.</div>
      @endif
      @php $oldSel = old('permissions', $selected); @endphp
      <div class="row g-3">
        @foreach ($groups as $group => $perms)
          <div class="col-md-6 col-xl-4">
            <div class="border rounded-3 p-3 h-100">
              <div class="fw-semibold mb-2">{{ $group }}</div>
              @foreach ($perms as $perm)
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->id }}" id="p{{ $perm->id }}"
                         @checked($isSuper || in_array($perm->id, $oldSel)) @disabled($isSuper)>
                  <label class="form-check-label" for="p{{ $perm->id }}">{{ $perm->label }} <code class="small">{{ $perm->name }}</code></label>
                </div>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
      <button class="btn btn-primary" type="submit">Simpan</button>
      <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
  </form>
@endsection
