@extends('admin._layout')
@php($isEdit = $device->exists)
@section('title', $isEdit ? 'Ubah Mesin Fingerprint' : 'Tambah Mesin Fingerprint')
@section('parent', 'Mesin Fingerprint')
@section('parent_url', route('admin.academic.fingerprint.devices.index'))
@section('content')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="card border-0 shadow-sm"><div class="card-body p-4">
    <form method="POST" action="{{ $isEdit ? route('admin.academic.fingerprint.devices.update', $device) : route('admin.academic.fingerprint.devices.store') }}">
      @csrf @if($isEdit) @method('PUT') @endif
      <div class="row g-3">
        <div class="col-md-6">
          <label for="serial_number" class="form-label">Nomor seri (SN) @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
          @if($isEdit)
            <input id="serial_number" class="form-control" value="{{ $device->serial_number }}" disabled>
            <div class="form-text">Nomor seri tidak dapat diubah karena dipakai mesin untuk mengenali dirinya.</div>
          @else
            <input id="serial_number" name="serial_number" value="{{ old('serial_number') }}" class="form-control @error('serial_number') is-invalid @enderror" maxlength="60" required>
            <div class="form-text">Tertera pada mesin (Menu → Info Sistem → Info Perangkat) atau di label belakang.</div>
          @endif
        </div>
        <div class="col-md-6">
          <label for="name" class="form-label">Nama mesin <span class="text-danger">*</span></label>
          <input id="name" name="name" value="{{ old('name', $device->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="120" required>
        </div>
        <div class="col-md-6">
          <label for="school_id" class="form-label">Sekolah</label>
          <select id="school_id" name="school_id" class="form-select @error('school_id') is-invalid @enderror">
            <option value="">Pilih sekolah</option>
            @foreach($schools as $school)
              <option value="{{ $school->id }}" @selected((string) old('school_id', $device->school_id) === (string) $school->id)>{{ $school->unit?->name }}</option>
            @endforeach
          </select>
          <div class="form-text">Log dari mesin ini hanya dicocokkan dengan siswa di sekolah yang dipilih.</div>
        </div>
        <div class="col-md-6">
          <label for="location" class="form-label">Lokasi pemasangan</label>
          <input id="location" name="location" value="{{ old('location', $device->location) }}" class="form-control" maxlength="160" placeholder="Mis. Gerbang utama">
        </div>
        <div class="col-12">
          <label for="allowed_ips" class="form-label">Batasi IP pengirim @if(config('student_attendance.require_allowed_ips'))<span class="text-danger">*</span>@else<span class="text-muted">(sangat disarankan)</span>@endif</label>
          <input id="allowed_ips" name="allowed_ips" value="{{ old('allowed_ips', $device->allowed_ips) }}" class="form-control @error('allowed_ips') is-invalid @enderror" maxlength="255" placeholder="Mis. 203.0.113.10, 10.0.0.0/24">
          <div class="form-text">Hanya IP/CIDR ini yang boleh mengirim data untuk mesin ini (dipisah koma). @if(config('student_attendance.require_allowed_ips'))Wajib diisi untuk mesin aktif, karena nomor seri saja bukan rahasia dan tidak cukup untuk membuktikan data berasal dari mesin Anda. Gunakan IP publik tetap sekolah (atau CIDR-nya).@else Tanpa batas IP, siapa pun yang tahu nomor seri mesin dapat mengirim log palsu. Kosongkan hanya bila IP internet sekolah berubah-ubah.@endif Salah isi membuat mesin ditolak (403).</div>
        </div>
        <div class="col-12">
          <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $device->is_active))>
            <label class="form-check-label" for="is_active">Aktif — terima log ketukan dari mesin ini</label>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary" type="submit"><x-icon name="check" size="16" class="me-1"/>Simpan</button>
        <a href="{{ route('admin.academic.fingerprint.devices.index') }}" class="btn btn-light">Batal</a>
      </div>
    </form>
  </div></div>
@endsection
