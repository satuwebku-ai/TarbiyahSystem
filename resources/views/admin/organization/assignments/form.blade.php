@extends('admin._layout')
@php $isEdit = $assignment->exists; @endphp
@section('title', $isEdit ? 'Ubah Penugasan' : 'Tambah Penugasan')
@section('parent', 'Penugasan Organisasi')
@section('parent_url', route('admin.organization.assignments.index'))
@section('content')
  @php
    $selectedUnit = old('organizational_unit_id', $assignment->organizational_unit_id);
    $selectedEmployee = old('employee_id', $assignment->employee_id);
    $selectedAdmin = old('admin_id', $assignment->admin_id);
  @endphp
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'assignments', 'compact' => true])</div>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      @if($units->isEmpty() || $positions->isEmpty())
        <div class="alert alert-info mb-0">Buat unit aktif dan jabatan aktif sebelum menambahkan penugasan.</div>
      @else
        @if($isEdit && ! $assignment->employee_id)
          <div class="alert alert-warning small">
            Penugasan ini masih memakai nama manual <strong>{{ $assignment->assignee_name }}</strong> dan belum terhubung ke Data Pegawai.
            Pilih pegawai yang sesuai agar namanya mengikuti Data Pegawai.
          </div>
        @endif
        <form method="POST" autocomplete="off" action="{{ $isEdit ? route('admin.organization.assignments.update', $assignment) : route('admin.organization.assignments.store') }}">
          @csrf
          @if($isEdit) @method('PUT') @endif
          <div class="row g-3">
            <div class="col-md-6">
              <label for="organizational_unit_id" class="form-label">Unit organisasi</label>
              <select id="organizational_unit_id" name="organizational_unit_id" required class="form-select @error('organizational_unit_id') is-invalid @enderror">
                <option value="">Pilih unit</option>
                @foreach($unitOptions as $option)<option value="{{ $option['id'] }}" @selected($selectedUnit == $option['id'])>{{ $option['label'] }} · {{ $option['code'] }}</option>@endforeach
              </select>
              @error('organizational_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text">Pilihan jabatan, pegawai, dan akun di bawah otomatis mengikuti unit yang dipilih.</div>
            </div>
            <div class="col-md-6">
              <label for="organizational_position_id" class="form-label">Jabatan</label>
              <select id="organizational_position_id" name="organizational_position_id" required class="form-select @error('organizational_position_id') is-invalid @enderror">
                <option value="">Pilih jabatan</option>
                @foreach($positions as $position)
                  <option value="{{ $position->id }}" data-unit-id="{{ $position->organizational_unit_id }}" @selected(old('organizational_position_id', $assignment->organizational_position_id) == $position->id)>
                    {{ $position->name }} · {{ $position->unit?->name }}
                  </option>
                @endforeach
              </select>
              @error('organizational_position_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text text-warning d-none" id="position-hint" data-base-href="{{ route('admin.organization.positions.create') }}">
                Unit ini belum memiliki jabatan aktif. <a href="#" id="position-hint-link">Tambah jabatan</a> terlebih dahulu.
              </div>
            </div>
            <div class="col-md-6">
              <label for="employee_id" class="form-label">Pegawai yang ditugaskan</label>
              <select id="employee_id" name="employee_id" required class="form-select @error('employee_id') is-invalid @enderror">
                <option value="">Pilih pegawai</option>
                @foreach($employees as $employee)
                  <option value="{{ $employee->id }}"
                          data-unit-ids='@json($employee->unit_ids)'
                          @if($assignment->employee_id == $employee->id) data-current="1" @endif
                          @selected($selectedEmployee == $employee->id)>
                    {{ $employee->name }} · {{ $employee->employee_number }} · {{ $employee->typeLabel() }}
                  </option>
                @endforeach
              </select>
              @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text">Nama diambil dari Data Pegawai. Hanya pegawai aktif yang ditempatkan di unit terpilih dan belum memegang jabatan lain yang muncul.</div>
              <div class="form-text text-warning d-none" id="employee-hint">
                Belum ada pegawai aktif yang ditempatkan di unit ini. Tambahkan penempatannya lewat Data Pegawai terlebih dahulu.
              </div>
            </div>
            <div class="col-md-6">
              <label for="admin_id" class="form-label">Akun login terkait (opsional)</label>
              <select id="admin_id" name="admin_id" class="form-select @error('admin_id') is-invalid @enderror">
                <option value="">Tanpa akun pengelola</option>
                @foreach($admins as $admin)
                  <option value="{{ $admin->id }}"
                          data-unit-ids='@json($admin->unit_ids)'
                          @if($assignment->admin_id == $admin->id) data-current="1" @endif
                          @selected($selectedAdmin == $admin->id)>
                    {{ $admin->name }} · {{ $admin->username ?: $admin->email }}
                  </option>
                @endforeach
              </select>
              @error('admin_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text">Hanya akun yang diberi Akses Unit pada unit terpilih. Ini hanya mengaitkan akun panel pengelola, bukan menggantikan data pegawai.</div>
            </div>
            <div class="col-md-6">
              <label for="start_date" class="form-label">Tanggal mulai</label>
              <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $assignment->start_date?->format('Y-m-d') ?? today()->toDateString()) }}" required class="form-control @error('start_date') is-invalid @enderror">
              @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label for="end_date" class="form-label">Tanggal akhir (opsional)</label>
              <input id="end_date" type="date" name="end_date" value="{{ old('end_date', $assignment->end_date?->format('Y-m-d')) }}" class="form-control @error('end_date') is-invalid @enderror">
              @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="alert alert-light border small mt-3 mb-0">Rentang penugasan pada jabatan yang sama tidak boleh tumpang tindih. Riwayat penugasan sebelumnya tetap tersimpan.</div>
          <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('admin.organization.assignments.index') }}" class="btn btn-light">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan penugasan</button>
          </div>
        </form>
      @endif
    </div>
  </div>
@endsection
@push('scripts')
<script>
  (() => {
    const unit = document.getElementById('organizational_unit_id');
    const position = document.getElementById('organizational_position_id');
    const employee = document.getElementById('employee_id');
    const admin = document.getElementById('admin_id');
    const hint = document.getElementById('position-hint');
    const hintLink = document.getElementById('position-hint-link');
    const employeeHint = document.getElementById('employee-hint');
    if (!unit || !position || !employee || !admin) return;

    const originalUnit = unit.value; // unit saat form dibuka (penugasan yang sedang diubah)

    const setVisible = (option, show) => {
      option.hidden = !show;
      option.disabled = !show; // sebagian peramban tidak menyembunyikan <option>
    };

    // Menyaring opsi sebuah <select> menurut unit terpilih; mengembalikan jumlah opsi yang tampil.
    const filterByUnit = (select, matcher) => {
      let visible = 0;
      Array.from(select.options).forEach((option) => {
        if (!option.value) return;
        // Pilihan milik penugasan lama tetap tampil selama unitnya tidak diganti.
        const keep = option.dataset.current === '1' && unit.value === originalUnit;
        const show = !!unit.value && (matcher(option) || keep);
        setVisible(option, show);
        if (show) visible++;
        if (!show && option.selected) select.value = '';
      });
      return visible;
    };

    const unitIdsOf = (option) => {
      try { return JSON.parse(option.dataset.unitIds || '[]').map(String); } catch (e) { return []; }
    };

    const refresh = () => {
      const positions = filterByUnit(position, (o) => o.dataset.unitId === unit.value);
      if (hint) {
        hint.classList.toggle('d-none', !unit.value || positions > 0);
        if (hintLink) hintLink.href = hint.dataset.baseHref + '?unit_id=' + encodeURIComponent(unit.value);
      }
      const employees = filterByUnit(employee, (o) => unitIdsOf(o).includes(unit.value));
      if (employeeHint) employeeHint.classList.toggle('d-none', !unit.value || employees > 0);
      filterByUnit(admin, (o) => unitIdsOf(o).includes(unit.value));
    };

    unit.addEventListener('change', refresh);
    refresh();
  })();
</script>
@endpush
