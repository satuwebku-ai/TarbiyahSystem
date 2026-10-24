@extends('admin._layout')
@section('title', 'Role & Permission')
@section('actions')
  @can('roles.create') <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" class="me-1"/>Tambah Role</a> @endcan
@endsection
@section('content')
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblRoles',
      'url' => route('admin.roles.index'),
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada role.',
      'columns' => [
        ['data' => 'label', 'title' => 'Role'],
        ['data' => 'name', 'title' => 'Kode'],
        ['data' => 'permissions_count', 'title' => 'Permission', 'searchable' => false, 'className' => 'text-center', 'headClass' => 'text-center'],
        ['data' => 'admins_count', 'title' => 'Akun', 'searchable' => false, 'className' => 'text-center', 'headClass' => 'text-center'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
      ],
    ])
  </div>
@endsection
