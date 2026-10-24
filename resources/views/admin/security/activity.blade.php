@extends('admin._layout')
@section('title', 'Riwayat Keamanan')
@section('parent', 'Pengaturan Admin')
@section('parent_url', route('admin.security.index'))
@section('actions')
  @can('security.view')
    <a href="{{ route('admin.security.index') }}" class="btn btn-outline-primary">
      <x-icon name="lock" size="16" class="me-1"/>Kebijakan Keamanan
    </a>
  @endcan
@endsection
@section('content')
  <div class="alert alert-light border d-flex align-items-start gap-2">
    <x-icon name="info" size="18" class="text-primary mt-1"/>
    <div>Riwayat mencatat login admin dan pegawai (berhasil, gagal, atau diblokir sementara), perubahan password, akun admin, role, dan kebijakan keamanan.</div>
  </div>
  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblActivity',
      'url' => route('admin.security.activity'),
      'order' => [[0, 'desc']],
      'length' => 25,
      'empty' => 'Belum ada aktivitas keamanan.',
      'columns' => [
        ['data' => 'created_at', 'title' => 'Waktu', 'searchable' => false],
        ['data' => 'event', 'title' => 'Aktivitas'],
        ['data' => 'actor_name', 'title' => 'Admin'],
        ['data' => 'subject_type', 'title' => 'Objek', 'orderable' => false],
        ['data' => 'ip_address', 'title' => 'Alamat IP'],
      ],
    ])
  </div>
@endsection
