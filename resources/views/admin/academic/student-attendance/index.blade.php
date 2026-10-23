@extends('admin._layout')
@section('title', 'Absensi Siswa')
@section('parent', 'Pendidikan')
@section('actions')
  <a href="{{ route('admin.academic.student-attendance.report') }}" class="btn btn-outline-primary btn-sm">Lihat rekap</a>
@endsection
@section('content')
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent fw-semibold">Pilih kelas, tanggal, dan sesi</div>
    <div class="card-body">
      <form method="GET" action="{{ route('admin.academic.student-attendance.index') }}" class="row g-3 align-items-end">
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'required' => true,
          'selected' => ['komwil' => request('komwil_id'), 'komda' => request('komda_id'), 'school' => request('school_id', $selectedSchool?->id)],
          'cols' => ['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-6'],
        ])
        <div class="col-md-6">
          <label for="classroom_id" class="form-label small mb-1">Kelas</label>
          <select id="classroom_id" name="classroom_id" class="form-select" required>
            <option value="">Pilih kelas</option>
            @foreach($classrooms as $classroom)
              <option value="{{ $classroom->id }}" data-school-id="{{ $classroom->school_id }}" data-year-id="{{ $classroom->academic_year_id }}" @selected((string) request('classroom_id', $selectedClassroom?->id) === (string) $classroom->id)>
                {{ $classroom->name }} · {{ $classroom->academicYear?->name }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label for="date" class="form-label small mb-1">Tanggal</label>
          <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label for="session_name" class="form-label small mb-1">Sesi</label>
          <input id="session_name" name="session_name" value="{{ $sessionName }}" class="form-control" maxlength="60" required>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
          <button class="btn btn-primary" type="submit">Muat daftar siswa</button>
          <a href="{{ route('admin.academic.student-attendance.index') }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>

  @if($selectedClassroom)
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <div class="fw-semibold">{{ $selectedClassroom->school?->unit?->name }} · {{ $selectedClassroom->name }}</div>
          <div class="small text-secondary">{{ $selectedClassroom->academicYear?->name }} · {{ $date->format('d M Y') }} · Sesi {{ $sessionName }}</div>
        </div>
        @if($records->isNotEmpty())
          <span class="badge text-bg-success">Tersimpan · {{ $records->count() }} siswa</span>
        @endif
      </div>
      <div class="card-body">
        @if(! $dateWithinYear)
          <div class="alert alert-warning mb-0">Tanggal yang dipilih berada di luar periode tahun ajaran {{ $selectedClassroom->academicYear?->name }}. Pilih tanggal di antara {{ $selectedClassroom->academicYear?->start_date?->format('d M Y') }} dan {{ $selectedClassroom->academicYear?->end_date?->format('d M Y') }}.</div>
        @elseif($roster->isEmpty())
          <div class="text-center text-secondary py-4">Tidak ada siswa aktif dengan penempatan di kelas ini pada tanggal tersebut.</div>
        @else
          @error('attendance') <div class="alert alert-danger">{{ $message }}</div> @enderror
          <form method="POST" action="{{ route('admin.academic.student-attendance.store') }}">
            @csrf
            <input type="hidden" name="school_id" value="{{ $selectedSchool->id }}">
            <input type="hidden" name="classroom_id" value="{{ $selectedClassroom->id }}">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="session_name" value="{{ $sessionName }}">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-3">
                <thead>
                  <tr><th style="width:52px">No.</th><th>Siswa</th><th style="min-width:160px">Status</th><th style="min-width:220px">Catatan</th></tr>
                </thead>
                <tbody>
                  @foreach($roster as $index => $student)
                    @php($record = $records->get($student->id))
                    <tr>
                      <td class="text-secondary">{{ $index + 1 }}</td>
                      <td>
                        <div class="fw-semibold">{{ $student->name }}</div>
                        <div class="small text-secondary">NIS {{ $student->nis }}</div>
                      </td>
                      <td>
                        <select name="attendance[{{ $student->id }}][status]" class="form-select form-select-sm" required>
                          @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('attendance.'.$student->id.'.status', $record?->status?->value ?? 'present') === $status->value)>{{ $status->label() }}</option>
                          @endforeach
                        </select>
                        @if($record?->source === 'fingerprint')<div class="small text-secondary mt-1"><x-icon name="clock" size="12" class="me-1"/>Fingerprint {{ \App\Helpers\AttendanceClock::toLocal($record->checked_in_at)?->format('H:i') }}</div>
                        @elseif($record?->source === 'auto')<div class="small text-secondary mt-1">Alpa otomatis</div>@endif
                      </td>
                      <td>
                        <input name="attendance[{{ $student->id }}][notes]" value="{{ old('attendance.'.$student->id.'.notes', $record?->notes) }}" class="form-control form-control-sm" maxlength="500" placeholder="Opsional">
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <div class="mb-3">
              <label for="correction_reason" class="form-label small mb-1">Alasan koreksi</label>
              <input id="correction_reason" name="correction_reason" value="{{ old('correction_reason') }}" class="form-control" maxlength="500" placeholder="Wajib diisi jika mengubah catatan yang sudah tersimpan">
              @error('correction_reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div class="small text-secondary">{{ $roster->count() }} siswa terdaftar. Menyimpan sesi yang sama akan memperbarui catatan, bukan membuat duplikat.</div>
              <button class="btn btn-primary" type="submit">Simpan absensi</button>
            </div>
          </form>

          @if($corrections->isNotEmpty())
            <hr class="my-4">
            <h2 class="h6 mb-3">Riwayat koreksi</h2>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Waktu</th><th>Siswa</th><th>Perubahan status</th><th>Alasan</th><th>Diubah oleh</th></tr></thead>
                <tbody>
                  @foreach($corrections as $entry)
                    @php($correction = $entry['correction'])
                    <tr>
                      <td class="text-nowrap">{{ $correction->created_at->format('d M Y H:i') }}</td>
                      <td>{{ $entry['student']?->name ?? 'Siswa tidak tersedia' }}</td>
                      <td>
                        {{ \App\Enums\StudentAttendanceStatus::tryFrom($correction->previous_status)?->label() ?? $correction->previous_status }}
                        <span class="text-secondary">→</span>
                        {{ \App\Enums\StudentAttendanceStatus::tryFrom($correction->new_status)?->label() ?? $correction->new_status }}
                        @if($correction->previous_notes !== $correction->new_notes)
                          <div class="small text-secondary">{{ $correction->previous_notes ?: 'Tanpa catatan' }} → {{ $correction->new_notes ?: 'Tanpa catatan' }}</div>
                        @endif
                      </td>
                      <td>{{ $correction->reason }}</td>
                      <td>{{ $correction->changedBy?->name ?? 'Akun tidak tersedia' }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        @endif
      </div>
    </div>
  @else
    <div class="alert alert-info">Pilih sekolah dan kelas untuk memuat siswa yang terdaftar pada tanggal tersebut.</div>
  @endif
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
  @endpush
@endsection
