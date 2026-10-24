@extends('admin._layout')
@section('title', 'Akun Pengelola')
@section('actions')
  @can('admins.create') <a href="{{ route('admin.admins.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" class="me-1"/>Tambah Akun</a> @endcan
@endsection
@section('content')
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblAdmins',
      'url' => route('admin.admins.index'),
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada akun pengelola.',
      'length' => 15,
      'columns' => [
        ['data' => 'name', 'title' => 'Nama'],
        ['data' => 'roles', 'title' => 'Role', 'orderable' => false, 'searchable' => false],
        ['data' => 'scope', 'title' => 'Cakupan unit', 'orderable' => false, 'searchable' => false],
        ['data' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'last_login_at', 'title' => 'Login terakhir', 'searchable' => false],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
      ],
    ])
  </div>
@endsection
