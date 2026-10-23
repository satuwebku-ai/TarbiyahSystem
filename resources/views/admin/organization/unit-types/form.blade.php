@extends('admin._layout')
@php($isEdit = $type->exists)
@section('title', $isEdit ? 'Ubah Jenis Unit' : 'Tambah Jenis Unit')
@section('parent', 'Jenis Unit')
@section('parent_url', route('admin.organization.unit-types.index'))
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'types', 'compact' => true])</div>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form method="POST" action="{{ $isEdit ? route('admin.organization.unit-types.update', $type) : route('admin.organization.unit-types.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="name">Nama jenis</label>
            <input id="name" name="name" value="{{ old('name', $type->name) }}" required maxlength="100" class="form-control @error('name') is-invalid @enderror">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="slug">Kode jenis</label>
            <input id="slug" name="slug" value="{{ old('slug', $type->slug) }}" required maxlength="100" pattern="[A-Za-z0-9_-]+" class="form-control @error('slug') is-invalid @enderror">
            <div class="form-text">Gunakan huruf, angka, tanda hubung, atau garis bawah.</div>
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label class="form-label" for="description">Keterangan</label>
            <textarea id="description" name="description" rows="3" maxlength="500" class="form-control @error('description') is-invalid @enderror">{{ old('description', $type->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <input type="hidden" name="is_active" value="0">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $type->exists ? $type->is_active : true))>
              <label class="form-check-label" for="is_active">Jenis unit aktif dan dapat dipilih</label>
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="{{ route('admin.organization.unit-types.index') }}" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan jenis</button>
        </div>
      </form>
    </div>
  </div>
@endsection
