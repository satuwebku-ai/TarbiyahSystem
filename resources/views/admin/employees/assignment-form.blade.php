@extends('admin._layout')
@php($isEdit = $assignment->exists)
@section('title', $isEdit ? 'Ubah Penempatan' : 'Tambah Penempatan')
@section('parent', $employee->name)
@section('parent_url', route('admin.employees.show', $employee))
@section('content')
  @include('partials.admin.select2-assets')
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form method="POST" action="{{ $isEdit ? route('admin.employees.assignments.update', [$employee, $assignment]) : route('admin.employees.assignments.store', $employee) }}">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="row g-3">
          <div class="col-md-6">
            <label for="organizational_unit_id" class="form-label">Unit</label>
            @include('partials.admin.unit-select', ['unitOptions' => $unitOptions ?? [], 'selected' => old('organizational_unit_id', $assignment->organizational_unit_id)])
            @error('organizational_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="organizational_position_id" class="form-label">Jabatan</label>
            <select id="organizational_position_id" name="organizational_position_id" class="form-select @error('organizational_position_id') is-invalid @enderror">
              <option value="">Tanpa jabatan</option>
              @foreach($positions as $position)<option value="{{ $position->id }}" data-unit-id="{{ $position->organizational_unit_id }}" @selected(old('organizational_position_id', $assignment->organizational_position_id) == $position->id)>{{ $position->name }}</option>@endforeach
            </select>
            @error('organizational_position_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="start_date" class="form-label">Mulai</label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $assignment->start_date?->toDateString()) }}" required class="form-control @error('start_date') is-invalid @enderror">
            @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label for="end_date" class="form-label">Selesai (kosong = berjalan)</label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date', $assignment->end_date?->toDateString()) }}" class="form-control @error('end_date') is-invalid @enderror">
            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <input type="hidden" name="is_primary" value="0">
            <div class="form-check">
              <input id="is_primary" class="form-check-input @error('is_primary') is-invalid @enderror" type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $assignment->is_primary))>
              <label for="is_primary" class="form-check-label">Penempatan utama</label>
              @error('is_primary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-12">
            <label for="notes" class="form-label">Catatan</label>
            <input id="notes" name="notes" value="{{ old('notes', $assignment->notes) }}" maxlength="500" class="form-control">
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan penempatan</button>
        </div>
      </form>
    </div>
  </div>

@push('scripts')
@include('partials.admin.unit-position-sync')
@endpush
@endsection
