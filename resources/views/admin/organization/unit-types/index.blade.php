@extends('admin._layout')
@section('title', 'Jenis Unit')
@section('actions')
  @can('organization.types.manage')
    <a href="{{ route('admin.organization.unit-types.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah jenis</a>
  @endcan
@endsection
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'types', 'compact' => true])</div>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
      <div class="fw-semibold">Jenis unit organisasi</div>
      <div class="small text-secondary">Jenis bersifat dinamis. Nonaktifkan jenis yang tidak lagi dipakai; data unit yang sudah ada tetap tersimpan.</div>
    </div>
    @include('partials.admin.datatable', [
      'id' => 'tblUnitTypes',
      'url' => route('admin.organization.unit-types.index'),
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada jenis unit. Tambahkan jenis sesuai kebutuhan yayasan.',
      'columns' => [
        ['data' => 'name', 'title' => 'Nama'],
        ['data' => 'slug', 'title' => 'Kode'],
        ['data' => 'units_count', 'title' => 'Jumlah unit', 'searchable' => false],
        ['data' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
@endsection
