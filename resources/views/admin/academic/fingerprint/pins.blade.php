@extends('admin._layout')
@section('title', 'Pemetaan PIN Fingerprint')
@section('parent', 'Absensi Siswa')
@section('parent_url', route('admin.academic.student-attendance.index'))
@section('content')
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.academic.fingerprint.pins.index') }}" class="row g-2 align-items-end" data-ajax-filter data-ajax-nopush>
        <div class="col-md-4"><label class="form-label small mb-1" for="school_id">Sekolah</label>
          <select id="school_id" name="school_id" class="form-select" required data-ajax-full onchange="var c=this.form.querySelector('[name=classroom_id]');if(c)c.value='';var q=this.form.querySelector('[name=q]');if(q)q.value='';if(this.value)this.form.submit()">
            <option value="">Pilih sekolah</option>
            @foreach($schools as $s)<option value="{{ $s->id }}" @selected($school?->id === $s->id)>{{ $s->unit?->name }}</option>@endforeach</select></div>
        @if($school)
          <div class="col-md-3"><label class="form-label small mb-1" for="classroom_id">Kelas (opsional)</label>
            <select id="classroom_id" name="classroom_id" class="form-select"><option value="">Semua siswa aktif</option>
              @foreach($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name }} · {{ $c->academicYear?->name }}</option>@endforeach</select></div>
          <div class="col-md-3"><label class="form-label small mb-1" for="q">Cari nama / NIS</label><input id="q" name="q" value="{{ request('q') }}" class="form-control" autocomplete="off" data-ajax-live></div>
        @endif
        <div class="col-auto"><button class="btn btn-primary" type="submit">Tampilkan</button></div>
      </form>
    </div>
  </div>

  <div data-ajax-region>
  @fragment('table')
  @if($school)
    @error('pins')<div class="alert alert-danger mb-0">{{ $message }}</div>@enderror
    @if($errors->has('pins.*'))<div class="alert alert-danger mb-0">{{ $errors->first('pins.*') }}</div>@endif
    <form method="POST" action="{{ route('admin.academic.fingerprint.pins.update') }}" class="card border-0 shadow-sm">
      @csrf @method('PUT')
      <input type="hidden" name="school_id" value="{{ $school->id }}">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="fw-semibold">{{ $school->unit?->name }}@if($classroom) · {{ $classroom->name }}@endif</div>
        <div class="small text-secondary">{{ $filledCount }} dari {{ $students->count() }} siswa sudah punya PIN</div>
      </div>
      @if($truncated)<div class="alert alert-warning rounded-0 mb-0 small">Menampilkan {{ $limit }} siswa pertama. Pilih kelas atau gunakan pencarian untuk mempersempit daftar.</div>@endif
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th style="width:52px">No.</th><th>Siswa</th><th style="width:220px">PIN di mesin</th></tr></thead>
          <tbody>
            @forelse($students as $i => $student)
              <tr>
                <td class="text-secondary">{{ $i + 1 }}</td>
                <td><div class="fw-semibold">{{ $student->name }}</div><div class="small text-secondary">NIS {{ $student->nis }}</div></td>
                <td><input name="pins[{{ $student->id }}]" value="{{ old('pins.'.$student->id, $student->fingerprint_pin) }}" class="form-control form-control-sm" maxlength="30" inputmode="numeric" autocomplete="off"></td>
              </tr>
            @empty
              <tr><td colspan="3" class="empty-state py-5">Tidak ada siswa aktif.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-transparent d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <span class="small text-secondary">Kosongkan untuk melepas PIN. Setelah disimpan, log lama yang PIN-nya baru dipetakan ikut diproses.</span>
        @if($students->isNotEmpty())<button class="btn btn-primary" type="submit"><x-icon name="check" size="16" class="me-1"/>Simpan PIN</button>@endif
      </div>
    </form>
  @endif
  @endfragment
  </div>
  @push('scripts')
    @include('partials.admin.ajax-table')
  @endpush
@endsection
