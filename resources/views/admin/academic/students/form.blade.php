@extends('admin._layout')
@section('title', $student->exists ? 'Ubah Data Siswa' : 'Tambah Siswa')
@section('parent', 'Data Siswa')
@section('parent_url', route('admin.academic.students.index'))
@section('content')
  @php($editing = $student->exists)
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form method="POST" action="{{ $editing ? route('admin.academic.students.update', $student) : route('admin.academic.students.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3">
          @if($editing)
            <div class="col-md-6">
              <label class="form-label">Sekolah</label>
              <input class="form-control" value="{{ $student->school?->unit?->name }} · {{ $student->school?->level?->name }}" disabled>
              <div class="form-text">Riwayat pindah sekolah dicatat melalui pendaftaran tahun ajaran, bukan mengubah sekolah asal secara langsung.</div>
            </div>
          @else
            <div class="col-md-6">
              <label for="school_id" class="form-label">Sekolah <span class="text-danger">*</span></label>
              <select id="school_id" name="school_id" class="form-select @error('school_id') is-invalid @enderror" required>
                <option value="">Pilih sekolah</option>
                @foreach($schools as $school)
                  <option value="{{ $school->id }}" @selected((string) old('school_id') === (string) $school->id)>{{ $school->unit?->name }} · {{ $school->level?->name }}</option>
                @endforeach
              </select>
              @error('school_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              @if($schools->isEmpty())<div class="form-text">Belum ada sekolah aktif dalam cakupan akses Anda.</div>@endif
            </div>
          @endif
          <div class="col-md-6">
            <label for="student_account_id" class="form-label">Akun portal siswa</label>
            <select id="student_account_id" name="student_account_id" class="form-select @error('student_account_id') is-invalid @enderror">
              <option value="">Belum dihubungkan</option>
              @foreach($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) old('student_account_id', $student->student_account_id) === (string) $account->id)>{{ $account->name }} · NIS {{ $account->nis }}</option>
              @endforeach
            </select>
            @error('student_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Akun yang sudah dipakai siswa lain tidak tersedia.</div>
          </div>
          <div class="col-md-6">
            <label for="nis" class="form-label">NIS <span class="text-danger">*</span></label>
            <input id="nis" name="nis" value="{{ old('nis', $student->nis) }}" class="form-control @error('nis') is-invalid @enderror" maxlength="30" required>
            @error('nis')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="nisn" class="form-label">NISN <span class="text-secondary">(opsional)</span></label>
            <input id="nisn" name="nisn" value="{{ old('nisn', $student->nisn) }}" class="form-control @error('nisn') is-invalid @enderror" maxlength="20">
            @error('nisn')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="fingerprint_pin" class="form-label">PIN fingerprint <span class="text-secondary">(opsional)</span></label>
            <input id="fingerprint_pin" name="fingerprint_pin" value="{{ old('fingerprint_pin', $student->fingerprint_pin) }}" class="form-control @error('fingerprint_pin') is-invalid @enderror" maxlength="30" inputmode="numeric" autocomplete="off">
            @error('fingerprint_pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Nomor ID siswa di mesin fingerprint. Satu PIN hanya untuk satu siswa di sekolah yang sama.</div>
          </div>
          <div class="col-md-6">
            <label for="name" class="form-label">Nama lengkap <span class="text-danger">*</span></label>
            <input id="name" name="name" value="{{ old('name', $student->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="160" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="gender" class="form-label">Jenis kelamin</label>
            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
              <option value="">Pilih</option>
              <option value="male" @selected(old('gender', $student->gender) === 'male')>Laki-laki</option>
              <option value="female" @selected(old('gender', $student->gender) === 'female')>Perempuan</option>
            </select>
            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="birth_place" class="form-label">Tempat lahir</label>
            <input id="birth_place" name="birth_place" value="{{ old('birth_place', $student->birth_place) }}" class="form-control @error('birth_place') is-invalid @enderror" maxlength="100">
            @error('birth_place')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="birth_date" class="form-label">Tanggal lahir</label>
            <input id="birth_date" type="date" name="birth_date" value="{{ old('birth_date', $student->birth_date?->format('Y-m-d')) }}" class="form-control @error('birth_date') is-invalid @enderror">
            @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
              @foreach(\App\Models\Student::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $student->status) === $value)>{{ $label }}</option>
              @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label for="address" class="form-label">Alamat</label>
            <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror" maxlength="500">{{ old('address', $student->address) }}</textarea>
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="{{ $editing ? route('admin.academic.students.show', $student) : route('admin.academic.students.index') }}" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary" @disabled(! $editing && $schools->isEmpty())>Simpan siswa</button>
        </div>
      </form>
    </div>
  </div>
@endsection
