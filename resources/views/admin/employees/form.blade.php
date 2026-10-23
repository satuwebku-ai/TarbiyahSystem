@extends('admin._layout')
@php($isEdit = $employee->exists)
@section('title', $isEdit ? 'Ubah Data Pegawai' : 'Tambah Pegawai')
@section('parent', 'Data Pegawai')
@section('parent_url', route('admin.employees.index'))
@section('content')
  @unless($isEdit) @include('partials.admin.select2-assets') @endunless
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      @if(! $isEdit && $units->isEmpty())
        <div class="alert alert-info mb-0">Belum ada unit organisasi aktif dalam cakupan Anda. Buat atau minta akses unit terlebih dahulu.</div>
      @else
      <form method="POST" action="{{ $isEdit ? route('admin.employees.update', $employee) : route('admin.employees.store') }}">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-3">
          <div class="col-md-4">
            <label for="employee_number" class="form-label">Nomor pegawai</label>
            <input id="employee_number" name="employee_number" value="{{ old('employee_number', $employee->employee_number) }}" required maxlength="40" class="form-control @error('employee_number') is-invalid @enderror">
            @error('employee_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-5">
            <label for="name" class="form-label">Nama lengkap</label>
            <input id="name" name="name" value="{{ old('name', $employee->name) }}" required maxlength="160" class="form-control @error('name') is-invalid @enderror">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-3">
            <label for="employee_type" class="form-label">Jenis</label>
            <select id="employee_type" name="employee_type" class="form-select @error('employee_type') is-invalid @enderror">
              @foreach(\App\Models\Employee::typeOptions(true, $employee->employee_type) as $key => $label)<option value="{{ $key }}" @selected(old('employee_type', $employee->employee_type) === $key)>{{ $label }}</option>@endforeach
            </select>
            @can('employees.types.manage')<div class="form-text"><a href="{{ route('admin.employees.types.index') }}">Kelola jenis pegawai</a></div>@endcan
            @error('employee_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $employee->email) }}" maxlength="160" class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="phone" class="form-label">Telepon</label>
            <input id="phone" name="phone" value="{{ old('phone', $employee->phone) }}" maxlength="30" class="form-control @error('phone') is-invalid @enderror">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="hire_date" class="form-label">Tanggal masuk</label>
            <input id="hire_date" type="date" name="hire_date" value="{{ old('hire_date', $employee->hire_date?->toDateString()) }}" class="form-control @error('hire_date') is-invalid @enderror">
            @error('hire_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label for="address" class="form-label">Alamat</label>
            <input id="address" name="address" value="{{ old('address', $employee->address) }}" maxlength="500" class="form-control @error('address') is-invalid @enderror">
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label for="notes" class="form-label">Catatan</label>
            <textarea id="notes" name="notes" rows="2" maxlength="2000" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $employee->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          @unless($isEdit)
            <div class="col-12"><hr class="my-1"><div class="fw-semibold">Penempatan awal</div></div>
            <div class="col-md-4">
              <label for="organizational_unit_id" class="form-label">Unit</label>
              @include('partials.admin.unit-select', ['unitOptions' => $unitOptions ?? [], 'selected' => old('organizational_unit_id')])
              @error('organizational_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label for="organizational_position_id" class="form-label">Jabatan</label>
              <select id="organizational_position_id" name="organizational_position_id" class="form-select @error('organizational_position_id') is-invalid @enderror">
                <option value="">Tanpa jabatan</option>
                @foreach($positions as $position)<option value="{{ $position->id }}" data-unit-id="{{ $position->organizational_unit_id }}" @selected(old('organizational_position_id') == $position->id)>{{ $position->name }}</option>@endforeach
              </select>
              <div class="form-text">Memilih jabatan otomatis membuat Penugasan Organisasi (tampil di bagan).</div>
              @error('organizational_position_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label for="start_date" class="form-label">Mulai bertugas</label>
              <input id="start_date" type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required class="form-control @error('start_date') is-invalid @enderror">
              @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

          @endunless

          @can('employees.link_account')
            <div class="col-12"><hr class="my-1"><div class="fw-semibold">Akun login portal</div></div>
            <div class="col-12">
              @if($isEdit && $employee->user_id)
                <div class="small mb-2">Akun terhubung: <strong>{{ $employee->user?->username }}</strong></div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="reset_password" name="reset_password" value="1" data-toggle-target="account-fields" @checked(old('reset_password'))>
                  <label class="form-check-label" for="reset_password">Ganti kata sandi akun ini</label>
                </div>
              @else
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="create_account" name="create_account" value="1" data-toggle-target="account-fields" @checked(old('create_account'))>
                  <label class="form-check-label" for="create_account">Buat akun login{{ $isEdit ? '' : ' sekarang' }} (agar pegawai bisa login dan absen sendiri)</label>
                </div>
                <div class="form-text">Wajib mengisi Email di atas. Pegawai login memakai username dan kata sandi ini.</div>
              @endif
            </div>
            <div id="account-fields" class="row g-3 mx-0 px-0 {{ old('create_account') || old('reset_password') ? '' : 'd-none' }}">
              @unless($isEdit && $employee->user_id)
                <div class="col-md-4">
                  <label for="username" class="form-label">Username</label>
                  <input id="username" name="username" value="{{ old('username') }}" maxlength="50" autocomplete="off" class="form-control @error('username') is-invalid @enderror">
                  @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              @endunless
              <div class="col-md-4">
                <label for="password" class="form-label">Kata sandi{{ $isEdit && $employee->user_id ? ' baru' : '' }}</label>
                <input id="password" type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label for="password_confirmation" class="form-label">Ulangi kata sandi</label>
                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="form-control">
              </div>
            </div>
          @endcan
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="{{ $isEdit ? route('admin.employees.show', $employee) : route('admin.employees.index') }}" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
      @endif
    </div>
  </div>

@push('scripts')
<script>
  document.querySelectorAll('[data-toggle-target]').forEach(function (box) {
    var target = document.getElementById(box.dataset.toggleTarget);
    var sync = function () { target.classList.toggle('d-none', !box.checked); };
    box.addEventListener('change', sync); sync();
  });
</script>
@endpush
@unless($isEdit)
@push('scripts')
@include('partials.admin.unit-position-sync')
@endpush
@endunless
@endsection
