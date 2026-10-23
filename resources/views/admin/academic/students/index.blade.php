@extends('admin._layout')
@section('title', 'Data Siswa')
@section('actions')
  @can('education.students.manage')
    <a href="{{ route('admin.academic.students.create') }}" class="btn btn-primary btn-sm">Tambah siswa</a>
  @endcan
@endsection
@section('content')
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.academic.students.index') }}" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label for="q" class="form-label small mb-1">Cari nama, NIS, atau NISN</label>
          <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control">
        </div>
        @include('partials.admin.school-filter', [
          'scope' => $scope,
          'selected' => ['komwil' => $filters['komwil_id'] ?? '', 'komda' => $filters['komda_id'] ?? '', 'school' => $filters['school_id'] ?? ''],
          'cols' => ['komwil' => 'col-md-2', 'komda' => 'col-md-2', 'school' => 'col-md-3'],
        ])
        <div class="col-md-2">
          <label for="status" class="form-label small mb-1">Status</label>
          <select id="status" name="status" class="form-select">
            <option value="">Semua</option>
            @foreach($statuses as $value => $label)
              <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto d-flex gap-2">
          <button class="btn btn-primary" type="submit">Cari</button>
          <a href="{{ route('admin.academic.students.index') }}" class="btn btn-light">Reset</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Siswa</th><th>Sekolah</th><th>Jenjang</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
          @forelse($students as $student)
            <tr>
              <td><div class="fw-semibold">{{ $student->name }}</div><div class="small text-secondary">NIS {{ $student->nis }} · NISN {{ $student->nisn ?: '—' }}</div></td>
              <td>{{ $student->school?->unit?->name ?? '—' }}</td>
              <td>{{ $student->school?->level?->name ?? '—' }}</td>
              <td><span class="badge text-bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ $statuses[$student->status] ?? $student->status }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.academic.students.show', $student) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                @can('education.students.manage')
                  <a href="{{ route('admin.academic.students.edit', $student) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-secondary py-5">Belum ada siswa pada cakupan Anda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($students->hasPages()) <div class="card-footer bg-transparent">{{ $students->links() }}</div> @endif
  </div>
  @push('scripts')
    @include('partials.admin.school-classroom-sync')
  @endpush
@endsection
