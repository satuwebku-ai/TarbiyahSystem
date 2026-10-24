@extends('admin._layout')
@section('title', 'Jenis Pegawai')
@section('parent', 'Data Pegawai')
@section('parent_url', route('admin.employees.index'))
@section('actions')
  <a href="{{ route('admin.employees.types.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah jenis</a>
@endsection
@section('content')
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
      <div class="fw-semibold">Jenis pegawai</div>
      <div class="small text-secondary">Contoh: Guru, Tenaga kependidikan, Satpam, Pengurus. Nonaktifkan jenis yang tidak dipakai; pegawai lama tetap tersimpan.</div>
    </div>
    @include('partials.admin.datatable', [
      'id' => 'tblEmployeeTypes',
      'url' => route('admin.employees.types.index'),
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada jenis pegawai.',
      'columns' => [
        ['data' => 'name', 'title' => 'Nama'],
        ['data' => 'code', 'title' => 'Kode'],
        ['data' => 'employees_total', 'title' => 'Jumlah pegawai', 'searchable' => false],
        ['data' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>
@endsection
