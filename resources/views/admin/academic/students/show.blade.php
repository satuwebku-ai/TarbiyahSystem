@extends('admin._layout')
@section('title', $student->name)
@section('parent', 'Data Siswa')
@section('parent_url', route('admin.academic.students.index'))
@section('actions')
  @can('education.students.manage')
    <a href="{{ route('admin.academic.students.edit', $student) }}" class="btn btn-outline-primary btn-sm">Ubah data</a>
  @endcan
@endsection
@section('content')
  <div class="row g-3 mb-3">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between gap-3">
            <div>
              <div class="small text-secondary">{{ $student->school?->unit?->name }} · {{ $student->school?->level?->name }}</div>
              <h2 class="h5 mt-1 mb-2">{{ $student->name }}</h2>
              <div class="small text-secondary">NIS {{ $student->nis }} · NISN {{ $student->nisn ?: 'belum diisi' }} · PIN fingerprint {{ $student->fingerprint_pin ?: 'belum diisi' }}</div>
            </div>
            <span class="badge align-self-start text-bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ \App\Models\Student::STATUSES[$student->status] ?? $student->status }}</span>
          </div>
          <hr>
          <div class="row g-3 small">
            <div class="col-md-4"><span class="text-secondary">Jenis kelamin</span><div class="fw-semibold">{{ ['male' => 'Laki-laki', 'female' => 'Perempuan'][$student->gender] ?? '—' }}</div></div>
            <div class="col-md-4"><span class="text-secondary">Tempat/tanggal lahir</span><div class="fw-semibold">{{ $student->birth_place ?: '—' }}{{ $student->birth_date ? ', '.$student->birth_date->format('d M Y') : '' }}</div></div>
            <div class="col-md-4"><span class="text-secondary">Akun portal</span><div class="fw-semibold">{{ $student->account?->name ?? 'Belum dihubungkan' }}</div></div>
            <div class="col-12"><span class="text-secondary">Alamat</span><div class="fw-semibold">{{ $student->address ?: '—' }}</div></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Wali siswa</h3><span class="badge text-bg-light">{{ $student->guardians->count() }}</span></div>
          @forelse($student->guardians as $guardian)
            <div class="border-bottom py-2">
              <div class="fw-semibold">{{ $guardian->name }} @if($guardian->is_primary)<span class="badge text-bg-primary">Utama</span>@endif</div>
              <div class="small text-secondary">{{ $guardian->relationship }} · {{ $guardian->phone ?: 'telepon belum diisi' }}</div>
              @if($guardian->email)<div class="small">{{ $guardian->email }}</div>@endif
            </div>
          @empty
            <p class="small text-secondary">Belum ada wali yang dicatat.</p>
          @endforelse
          @can('education.students.manage')
            <form method="POST" action="{{ route('admin.academic.students.guardians.store', $student) }}" class="row g-2 mt-2">
              @csrf
              <div class="col-12"><label class="form-label small mb-1" for="guardian_name">Nama wali</label><input id="guardian_name" name="name" class="form-control @error('name') is-invalid @enderror" maxlength="160" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              <div class="col-6"><label class="form-label small mb-1" for="guardian_relationship">Hubungan</label><input id="guardian_relationship" name="relationship" class="form-control @error('relationship') is-invalid @enderror" placeholder="Orang tua / wali" maxlength="60" required>@error('relationship')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              <div class="col-6"><label class="form-label small mb-1" for="guardian_phone">Telepon</label><input id="guardian_phone" name="phone" class="form-control" maxlength="30"></div>
              <div class="col-12"><label class="form-label small mb-1" for="guardian_email">Email</label><input id="guardian_email" type="email" name="email" class="form-control" maxlength="150"></div>
              <div class="col-12 form-check ms-2"><input id="guardian_primary" type="checkbox" name="is_primary" value="1" class="form-check-input"><label for="guardian_primary" class="form-check-label small">Jadikan wali utama</label></div>
              <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Tambah wali</button></div>
            </form>
          @endcan
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent"><h3 class="h6 mb-0">Riwayat pendaftaran & kelas</h3></div>
        <div class="card-body">
          @forelse($student->enrollments as $enrollment)
            <div class="border rounded-3 p-3 mb-3">
              <div class="d-flex justify-content-between">
                <div class="fw-semibold">{{ $enrollment->academicYear?->name }}</div>
                <span class="badge text-bg-{{ $enrollment->status === 'active' ? 'success' : 'secondary' }}">{{ \App\Models\StudentEnrollment::STATUSES[$enrollment->status] ?? $enrollment->status }}</span>
              </div>
              <div class="small text-secondary mb-2">Tanggal pendaftaran: {{ $enrollment->enrollment_date->format('d M Y') }}</div>
              @can('education.students.manage')
                <form method="POST" action="{{ route('admin.academic.students.enrollments.status', [$student, $enrollment]) }}" class="d-flex align-items-center gap-2 mb-2">
                  @csrf @method('PATCH')
                  <label class="small text-secondary" for="enrollment_status_{{ $enrollment->id }}">Status</label>
                  <select id="enrollment_status_{{ $enrollment->id }}" name="status" class="form-select form-select-sm" style="max-width:220px">
                    @foreach(\App\Models\StudentEnrollment::STATUSES as $value => $label)
                      <option value="{{ $value }}" @selected($enrollment->status === $value)>{{ $label }}</option>
                    @endforeach
                  </select>
                  <button class="btn btn-sm btn-outline-secondary" type="submit">Ubah</button>
                </form>
              @endcan
              @forelse($enrollment->placements as $placement)
                <div class="d-flex justify-content-between border-top py-2 small">
                  <span>{{ $placement->classroom?->name }} · mulai {{ $placement->start_date->format('d M Y') }}</span>
                  <span class="text-secondary">{{ $placement->end_date ? 's.d. '.$placement->end_date->format('d M Y') : 'Berjalan' }}</span>
                </div>
              @empty
                <div class="small text-secondary border-top pt-2">Belum ada penempatan kelas.</div>
              @endforelse
              @can('education.students.manage')
                @php($classOptions = $enrollment->academicYear?->classrooms ?? collect())
                @if($classOptions->isNotEmpty() && $enrollment->status === 'active')
                  <form method="POST" action="{{ route('admin.academic.students.placements.store', [$student, $enrollment]) }}" class="row g-2 mt-2">
                    @csrf
                    <div class="col-md-7">
                      <label class="form-label small mb-1">Pindah / tempatkan ke kelas</label>
                      <select name="classroom_id" class="form-select @error('classroom_id') is-invalid @enderror" required>
                        <option value="">Pilih kelas</option>
                        @foreach($classOptions as $classroom)<option value="{{ $classroom->id }}">{{ $classroom->name }}{{ $classroom->grade_label ? ' · tingkat '.$classroom->grade_label : '' }}</option>@endforeach
                      </select>
                      @error('classroom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3"><label class="form-label small mb-1">Mulai tanggal</label><input type="date" name="start_date" value="{{ today()->toDateString() }}" class="form-control @error('start_date') is-invalid @enderror" required>@error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-outline-primary w-100">Simpan</button></div>
                  </form>
                @endif
              @endcan
            </div>
          @empty
            <p class="text-secondary mb-0">Belum ada pendaftaran siswa ke tahun ajaran.</p>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-5">
      @can('education.students.manage')
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6">Daftarkan ke tahun ajaran</h3>
            @if($availableYears->isEmpty())
              <p class="small text-secondary">Sekolah belum memiliki tahun ajaran. Tambahkan dari <a href="{{ route('admin.academic.schools.show', $student->school) }}">halaman sekolah</a>.</p>
            @else
              <form method="POST" action="{{ route('admin.academic.students.enrollments.store', $student) }}" class="row g-2">
                @csrf
                <div class="col-12">
                  <label class="form-label small mb-1">Tahun ajaran</label>
                  <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                    <option value="">Pilih tahun ajaran</option>
                    @foreach($availableYears as $year)
                      @if(! $student->enrollments->contains('academic_year_id', $year->id))
                        <option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>
                      @endif
                    @endforeach
                  </select>
                  @error('academic_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6"><label class="form-label small mb-1">Tanggal daftar</label><input type="date" name="enrollment_date" value="{{ today()->toDateString() }}" class="form-control @error('enrollment_date') is-invalid @enderror" required>@error('enrollment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">Status</label>
                  <select name="status" class="form-select" required><option value="active">Aktif</option><option value="completed">Selesai</option><option value="transferred">Pindah</option><option value="withdrawn">Mengundurkan diri</option></select>
                </div>
                <div class="col-12"><label class="form-label small mb-1">Catatan</label><textarea name="notes" rows="2" class="form-control" maxlength="5000"></textarea></div>
                <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Simpan pendaftaran</button></div>
              </form>
            @endif
          </div>
        </div>
      @endcan
    </div>
  </div>
@endsection
