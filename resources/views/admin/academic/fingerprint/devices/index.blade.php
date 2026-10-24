@extends('admin._layout')
@section('title', 'Mesin Fingerprint')
@section('parent', 'Absensi Siswa')
@section('parent_url', route('admin.academic.student-attendance.index'))
@section('actions')
  @if($canManage)
    <a href="{{ route('admin.academic.fingerprint.pins.index') }}" class="btn btn-outline-primary btn-sm">Pemetaan PIN</a>
    <a href="{{ route('admin.academic.fingerprint.devices.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah mesin</a>
  @endif
@endsection
@section('content')
  @if($pendingCount > 0 && $canManage)
    <div class="alert alert-warning d-flex gap-2 align-items-start">
      <x-icon name="alert" size="18" class="mt-1 flex-shrink-0"/>
      <div><strong>{{ $pendingCount }} mesin baru terdeteksi.</strong> Mesin ini sudah menghubungi server tetapi belum dikaitkan dengan sekolah, sehingga log ketukannya belum diterima. Klik <em>Atur &amp; aktifkan</em> pada barisnya, pilih sekolah, lalu aktifkan.</div>
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    @include('partials.admin.datatable', [
      'id' => 'tblDevices',
      'url' => route('admin.academic.fingerprint.devices.index'),
      'order' => [[0, 'asc']],
      'empty' => 'Belum ada mesin. Arahkan mesin ke alamat server di bawah, atau tambahkan manual dengan nomor serinya.',
      'columns' => [
        ['data' => 'name', 'title' => 'Mesin'],
        ['data' => 'school_name', 'title' => 'Sekolah'],
        ['data' => 'connection', 'title' => 'Koneksi', 'searchable' => false],
        ['data' => 'logs', 'title' => 'Log', 'searchable' => false],
        ['data' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false],
        ['data' => 'action', 'title' => 'Tindakan', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap', 'headClass' => 'text-end'],
      ],
    ])
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Cara menyambungkan mesin</div>
    <div class="card-body small">
      <ol class="mb-3 ps-3">
        <li>Di mesin buka <strong>Menu → Komunikasi → Pengaturan Cloud Server (ADMS)</strong>.</li>
        <li>Isi <strong>Alamat server</strong> dengan alamat aplikasi ini (tanpa <code>http://</code> dan tanpa garis miring), <strong>Port</strong> sesuai server (80 bila tanpa HTTPS), lalu aktifkan.</li>
        <li>Mesin akan muncul di daftar ini sebagai <em>Baru terdeteksi</em>. Kaitkan ke sekolah, lalu aktifkan.</li>
        <li>Pada <a href="{{ route('admin.academic.fingerprint.pins.index') }}">Pemetaan PIN</a> (atau form siswa) isi PIN tiap siswa sesuai nomor ID di mesin.</li>
      </ol>
      <div class="p-3 rounded-3 bg-body-tertiary mb-2">
        Alamat endpoint: <code>{{ $pushUrl }}</code>
      </div>
      <div class="text-secondary">Banyak mesin ZKTeco/Solution lama hanya mendukung <strong>HTTP</strong>. Pastikan server tidak memaksa pengalihan ke HTTPS khusus untuk path <code>/iclock</code>. Waktu pada mesin sebaiknya sama dengan zona waktu absensi ({{ config('attendance.timezone') }}).</div>
    </div>
  </div>
@endsection
