@extends('admin._layout')
@section('title', $workProgram->exists ? 'Ubah Program Kerja' : 'Buat Program Kerja')
@section('parent', 'Program Kerja & Kegiatan')
@section('parent_url', route('admin.programs.index'))
@section('content')
  @php($editing = $workProgram->exists)
  <div class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <form method="POST" action="{{ $editing ? route('admin.programs.update', $workProgram) : route('admin.programs.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3">
          <div class="col-md-6">
            <label for="organizational_unit_id" class="form-label">Unit organisasi <span class="text-danger">*</span></label>
            <select id="organizational_unit_id" name="organizational_unit_id" class="form-select @error('organizational_unit_id') is-invalid @enderror" required>
              <option value="">Pilih unit</option>
              @foreach($units as $unit)
                <option value="{{ $unit->id }}" @selected((string) old('organizational_unit_id', $workProgram->organizational_unit_id) === (string) $unit->id)>{{ $unit->name }} ({{ $unit->code }})</option>
              @endforeach
            </select>
            @error('organizational_unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="code" class="form-label">Kode program <span class="text-danger">*</span></label>
            <input id="code" name="code" value="{{ old('code', $workProgram->code) }}" class="form-control @error('code') is-invalid @enderror" maxlength="40" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label for="title" class="form-label">Nama program <span class="text-danger">*</span></label>
            <input id="title" name="title" value="{{ old('title', $workProgram->title) }}" class="form-control @error('title') is-invalid @enderror" maxlength="180" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="start_date" class="form-label">Tanggal mulai <span class="text-danger">*</span></label>
            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $workProgram->start_date?->format('Y-m-d')) }}" class="form-control @error('start_date') is-invalid @enderror" required>
            @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="end_date" class="form-label">Tanggal selesai <span class="text-danger">*</span></label>
            <input id="end_date" type="date" name="end_date" value="{{ old('end_date', $workProgram->end_date?->format('Y-m-d')) }}" class="form-control @error('end_date') is-invalid @enderror" required>
            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label for="estimated_budget" class="form-label">Anggaran rencana (Rp) <span class="text-danger">*</span></label>
            <input id="estimated_budget" type="number" min="0" step="0.01" name="estimated_budget" value="{{ old('estimated_budget', $workProgram->estimated_budget ?? 0) }}" class="form-control @error('estimated_budget') is-invalid @enderror" required>
            @error('estimated_budget')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label for="description" class="form-label">Deskripsi</label>
            <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $workProgram->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label for="objectives" class="form-label">Target / tujuan</label>
            <textarea id="objectives" name="objectives" rows="3" class="form-control @error('objectives') is-invalid @enderror">{{ old('objectives', $workProgram->objectives) }}</textarea>
            @error('objectives')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="{{ $editing ? route('admin.programs.show', $workProgram) : route('admin.programs.index') }}" class="btn btn-light">Batal</a>
          <button type="submit" class="btn btn-primary">Simpan sebagai draft</button>
        </div>
      </form>
    </div>
  </div>
@endsection
