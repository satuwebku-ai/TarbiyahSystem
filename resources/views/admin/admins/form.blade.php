@extends('admin._layout')
@php $isEdit = $admin->exists; $oldRoles = old('roles', $selected); @endphp
@section('title', $isEdit ? 'Ubah Akun' : 'Tambah Akun')
@section('parent', 'Akun Pengelola')
@section('parent_url', route('admin.admins.index'))
@section('content')
  @if($canSetUnits ?? false) @include('partials.admin.select2-assets') @endif
  <form method="POST" action="{{ $isEdit ? route('admin.admins.update', $admin) : route('admin.admins.store') }}" class="card border-0 shadow-sm">
    @csrf
    @if($isEdit) @method('PUT') @endif
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <input name="name" value="{{ old('name', $admin->name) }}" class="form-control @error('name') is-invalid @enderror" required>
          @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Username <span class="text-secondary small">(huruf kecil, angka, titik, strip, underscore)</span></label>
          <input name="username" value="{{ old('username', $admin->username) }}" autocomplete="off" class="form-control @error('username') is-invalid @enderror" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._\-]+">
          @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" value="{{ old('email', $admin->email) }}" class="form-control @error('email') is-invalid @enderror" required>
          @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Kata sandi @if($isEdit)<span class="text-secondary small">(kosongkan bila tidak diubah)</span>@endif</label>
          <input type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" @required(! $isEdit)>
          @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">Ulangi kata sandi</label>
          <input type="password" name="password_confirmation" autocomplete="new-password" class="form-control">
        </div>
        <div class="col-12">
          <label class="form-label d-block">Role</label>
          @foreach ($roles as $r)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r->id }}" id="r{{ $r->id }}" @checked(in_array($r->id, $oldRoles))>
              <label class="form-check-label" for="r{{ $r->id }}">{{ $r->label }}</label>
            </div>
          @endforeach
          @error('roles.*') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
        @if($canSetUnits ?? false)
          <div class="col-12">
            <hr class="my-1">
            <label for="unit_ids" class="form-label fw-semibold mb-0">Cakupan unit</label>
            <div class="small text-secondary mb-2">Pilih unit tempat akun ini bertugas, misalnya <strong>Pengurus Besar</strong>, <strong>KOMWIL</strong>, atau <strong>KOMDA</strong>. Cukup pilih unit induknya; seluruh unit di bawahnya ikut otomatis. Akun tanpa unit tidak dapat melihat data organisasi apa pun.</div>
            <input type="hidden" name="unit_scope_present" value="1">
            <select id="unit_ids" name="unit_ids[]" multiple class="form-select js-unit-multi @error('unit_ids') is-invalid @enderror @error('unit_ids.*') is-invalid @enderror" data-placeholder="Cari atau pilih unit…">
              @foreach($unitOptions as $option)
                <option value="{{ $option['id'] }}" data-name="{{ $option['name'] }}" data-code="{{ $option['code'] }}" data-depth="{{ $option['depth'] }}" data-path="{{ $option['path'] }}"
                        @selected(in_array($option['id'], array_map('intval', old('unit_ids', $unitSelected)), true))>{{ $option['name'] }} · {{ $option['code'] }}</option>
              @endforeach
            </select>
            @error('unit_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @error('unit_ids.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Super Admin dan akun dengan izin "Akses semua unit organisasi" selalu memiliki akses penuh, sehingga pilihan ini tidak berpengaruh untuknya.</div>
          </div>
        @endif
        <div class="col-12">
          <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $isEdit ? $admin->is_active : true))>
            <label class="form-check-label" for="active">Akun aktif</label>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
      <button class="btn btn-primary" type="submit">Simpan</button>
      <a href="{{ route('admin.admins.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
  </form>
@endsection
