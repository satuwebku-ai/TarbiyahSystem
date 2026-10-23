@extends('admin._layout')
@section('title', $school->exists ? 'Ubah Data Sekolah' : 'Tambah Sekolah')
@section('parent', 'Data Sekolah')
@section('parent_url', route('admin.academic.schools.index'))
@section('content')
  @php($editing = $school->exists)
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form method="POST" action="{{ $editing ? route('admin.academic.schools.update', $school) : route('admin.academic.schools.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3">
          @if($editing)
            <div class="col-md-6">
              <label class="form-label">Kode unit sekolah</label>
              <input class="form-control" value="{{ $unit->code }}" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label">Berada di bawah</label>
              <input class="form-control" value="{{ $unit->parent?->name ?? '—' }}" disabled>
            </div>
          @else
            <div class="col-md-6">
              <label for="parent_unit_id" class="form-label">KOMDA <span class="text-danger">*</span></label>
              <select id="parent_unit_id" name="parent_unit_id" class="form-select @error('parent_unit_id') is-invalid @enderror" required>
                <option value="">Pilih KOMDA</option>
                @foreach($parentUnits as $parentUnit)
                  <option value="{{ $parentUnit->id }}" @selected((string) old('parent_unit_id') === (string) $parentUnit->id)>{{ $parentUnit->name }} ({{ $parentUnit->code }})</option>
                @endforeach
              </select>
              @error('parent_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              @if($parentUnits->isEmpty())<div class="form-text">Belum ada KOMDA aktif dalam cakupan Anda. Tambahkan unit KOMDA terlebih dahulu.</div>@endif
            </div>
            <div class="col-md-6">
              <label for="code" class="form-label">Kode sekolah <span class="text-danger">*</span></label>
              <input id="code" name="code" value="{{ old('code') }}" class="form-control @error('code') is-invalid @enderror" maxlength="40" required>
              @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          @endif
          <div class="col-md-6">
            <label for="name" class="form-label">Nama sekolah <span class="text-danger">*</span></label>
            <input id="name" name="name" value="{{ old('name', $unit?->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="160" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="school_level_id" class="form-label">Jenjang <span class="text-danger">*</span></label>
            <select id="school_level_id" name="school_level_id" class="form-select @error('school_level_id') is-invalid @enderror" required>
              <option value="">Pilih jenjang</option>
              @foreach($levels as $level)
                <option value="{{ $level->id }}" @selected((string) old('school_level_id', $school->school_level_id) === (string) $level->id)>{{ $level->name }}</option>
              @endforeach
            </select>
            @error('school_level_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="npsn" class="form-label">NPSN <span class="text-secondary">(opsional)</span></label>
            <input id="npsn" name="npsn" value="{{ old('npsn', $school->npsn) }}" class="form-control @error('npsn') is-invalid @enderror" maxlength="20">
            @error('npsn')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
              @foreach(\App\Models\School::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $school->status) === $value)>{{ $label }}</option>
              @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="phone" class="form-label">Telepon</label>
            <input id="phone" name="phone" value="{{ old('phone', $school->phone) }}" class="form-control @error('phone') is-invalid @enderror" maxlength="30">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $school->email) }}" class="form-control @error('email') is-invalid @enderror" maxlength="150">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label for="address" class="form-label">Alamat</label>
            <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror" maxlength="500">{{ old('address', $unit?->address) }}</textarea>
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="{{ $editing ? route('admin.academic.schools.show', $school) : route('admin.academic.schools.index') }}" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary" @disabled(! $editing && $parentUnits->isEmpty())>Simpan sekolah</button>
        </div>
      </form>
    </div>
  </div>
@endsection
