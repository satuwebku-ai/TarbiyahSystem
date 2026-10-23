@extends('admin._layout')
@section('title', $school->unit?->name ?? 'Detail Sekolah')
@section('parent', 'Data Sekolah')
@section('parent_url', route('admin.academic.schools.index'))
@section('actions')
  @can('education.schools.manage')
    <a href="{{ route('admin.academic.schools.edit', $school) }}" class="btn btn-outline-primary btn-sm">Ubah sekolah</a>
  @endcan
@endsection
@section('content')
  <div class="row g-3 mb-3">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
              <div class="text-secondary small">{{ $school->unit?->code }} · {{ $school->level?->name }}</div>
              <h2 class="h5 mt-1 mb-2">{{ $school->unit?->name }}</h2>
              <div class="text-secondary">{{ $school->unit?->address ?: 'Alamat belum diisi' }}</div>
            </div>
            <span class="badge align-self-start text-bg-{{ $school->status === 'active' ? 'success' : 'secondary' }}">{{ \App\Models\School::STATUSES[$school->status] ?? $school->status }}</span>
          </div>
          <hr>
          <div class="row g-3 small">
            <div class="col-md-4"><span class="text-secondary">KOMDA</span><div class="fw-semibold">{{ $school->unit?->parent?->name ?? '—' }}</div></div>
            <div class="col-md-4"><span class="text-secondary">NPSN</span><div class="fw-semibold">{{ $school->npsn ?: 'Belum diisi' }}</div></div>
            @if($canViewStudents)<div class="col-md-4"><span class="text-secondary">Siswa terdaftar</span><div class="fw-semibold">{{ number_format($school->students_count) }}</div></div>@endif
            <div class="col-md-4"><span class="text-secondary">Telepon</span><div class="fw-semibold">{{ $school->phone ?: '—' }}</div></div>
            <div class="col-md-4"><span class="text-secondary">Email</span><div class="fw-semibold">{{ $school->email ?: '—' }}</div></div>
          </div>
        </div>
      </div>
    </div>
    @if($canViewStudents)
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h3 class="h6">Siswa terbaru</h3>
            @forelse($students as $student)
              <div class="d-flex justify-content-between py-2 border-bottom">
                <div><a href="{{ route('admin.academic.students.show', $student) }}" class="fw-semibold text-decoration-none">{{ $student->name }}</a><div class="small text-secondary">NIS {{ $student->nis }}</div></div>
                <span class="small">{{ \App\Models\Student::STATUSES[$student->status] ?? $student->status }}</span>
              </div>
            @empty
              <p class="text-secondary small mb-0">Belum ada siswa.</p>
            @endforelse
            <a href="{{ route('admin.academic.students.index', ['school_id' => $school->id]) }}" class="btn btn-sm btn-outline-secondary mt-3">Lihat semua siswa</a>
          </div>
        </div>
      </div>
    @endif
  </div>

  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center"><h3 class="h6 mb-0">Tahun ajaran & kelas</h3><span class="badge text-bg-light">{{ $years->count() }} tahun ajaran</span></div>
        <div class="card-body">
          @forelse($years as $year)
            <div class="border rounded-3 p-3 mb-3">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold">{{ $year->name }} @if($year->is_active)<span class="badge text-bg-success ms-1">Aktif</span>@endif</div>
                  <div class="small text-secondary">{{ $year->start_date->format('d M Y') }} – {{ $year->end_date->format('d M Y') }} @if($canViewStudents) · {{ $year->enrollments_count }} pendaftaran @endif</div>
                </div>
                @if(! $year->is_active)
                  @can('education.academic.manage')
                    <form method="POST" action="{{ route('admin.academic.schools.academic-years.activate', [$school, $year]) }}">
                      @csrf @method('PATCH')
                      <button class="btn btn-sm btn-outline-success" type="submit">Aktifkan</button>
                    </form>
                  @endcan
                @endif
              </div>
              <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0">
                  <thead><tr><th>Kelas</th><th>Tingkat</th><th>Kapasitas</th>@if($canViewStudents)<th>Penempatan</th>@endif</tr></thead>
                  <tbody>
                    @forelse($year->classrooms as $classroom)
                      <tr><td>{{ $classroom->name }}</td><td>{{ $classroom->grade_label ?: '—' }}</td><td>{{ $classroom->capacity ?: '—' }}</td>@if($canViewStudents)<td>{{ $classroom->placements_count }}</td>@endif</tr>
                    @empty
                      <tr><td colspan="{{ $canViewStudents ? 4 : 3 }}" class="text-secondary">Belum ada kelas.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          @empty
            <p class="text-secondary mb-0">Belum ada tahun ajaran.</p>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-5">
      @can('education.academic.manage')
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body">
            <h3 class="h6">Tambah tahun ajaran</h3>
            <form method="POST" action="{{ route('admin.academic.schools.academic-years.store', $school) }}" class="row g-2">
              @csrf
              <div class="col-12"><label class="form-label small mb-1" for="year_name">Nama/periode</label><input id="year_name" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="2027/2028" maxlength="30" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              <div class="col-6"><label class="form-label small mb-1" for="year_start">Mulai</label><input id="year_start" type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" required>@error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              <div class="col-6"><label class="form-label small mb-1" for="year_end">Selesai</label><input id="year_end" type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" required>@error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              <div class="col-12 form-check ms-2"><input id="year_active" type="checkbox" name="is_active" value="1" class="form-check-input"><label for="year_active" class="form-check-label small">Jadikan tahun ajaran aktif</label></div>
              <div class="col-12"><button class="btn btn-primary btn-sm" type="submit">Simpan tahun ajaran</button></div>
            </form>
          </div>
        </div>
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6">Tambah kelas</h3>
            <form method="POST" action="{{ route('admin.academic.schools.classrooms.store', $school) }}" class="row g-2">
              @csrf
              <div class="col-12">
                <label class="form-label small mb-1" for="class_year">Tahun ajaran</label>
                <select id="class_year" name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                  <option value="">Pilih tahun ajaran</option>
                  @foreach($years as $year)<option value="{{ $year->id }}">{{ $year->name }}{{ $year->is_active ? ' (aktif)' : '' }}</option>@endforeach
                </select>
                @error('academic_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-7"><label class="form-label small mb-1" for="class_name">Nama kelas</label><input id="class_name" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Kelas 1A" maxlength="100" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
              <div class="col-md-5"><label class="form-label small mb-1" for="grade_label">Tingkat</label><input id="grade_label" name="grade_label" class="form-control" maxlength="40" placeholder="1"></div>
              <div class="col-12"><label class="form-label small mb-1" for="capacity">Kapasitas siswa</label><input id="capacity" type="number" name="capacity" min="1" max="2000" class="form-control" placeholder="Opsional"></div>
              <div class="col-12"><button class="btn btn-primary btn-sm" type="submit" @disabled($years->isEmpty())>Simpan kelas</button></div>
            </form>
          </div>
        </div>
      @endcan
    </div>
  </div>
@endsection
