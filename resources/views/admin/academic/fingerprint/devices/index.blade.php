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
  @php($pending = $devices->filter->isPending())
  @if($pending->isNotEmpty() && $canManage)
    <div class="alert alert-warning d-flex gap-2 align-items-start">
      <x-icon name="alert" size="18" class="mt-1 flex-shrink-0"/>
      <div><strong>{{ $pending->count() }} mesin baru terdeteksi.</strong> Mesin ini sudah menghubungi server tetapi belum dikaitkan dengan sekolah, sehingga log ketukannya belum diterima. Klik <em>Atur &amp; aktifkan</em> pada barisnya, pilih sekolah, lalu aktifkan.</div>
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Mesin</th><th>Sekolah</th><th>Koneksi</th><th>Log</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($devices as $device)
            <tr>
              <td>
                <div class="fw-semibold">{{ $device->name }}</div>
                <div class="small text-secondary">SN {{ $device->serial_number }}@if($device->location) · {{ $device->location }}@endif</div>
              </td>
              <td>{{ $device->school?->unit?->name ?? '—' }}</td>
              <td>
                <span class="badge {{ $device->isOnline() ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $device->isOnline() ? 'Terhubung' : 'Tidak terhubung' }}</span>
                <div class="small text-secondary">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Belum pernah' }}@if($device->last_ip) · {{ $device->last_ip }}@endif</div>
              </td>
              <td>
                {{ number_format($device->total_logs) }}
                @if($device->unmapped_count)
                  <a href="{{ route('admin.academic.fingerprint.logs.index', ['device_id' => $device->id, 'result' => 'unmapped']) }}" class="badge badge-soft-danger text-decoration-none">{{ $device->unmapped_count }} PIN belum terpetakan</a>
                @endif
                @if($device->last_log_at)<div class="small text-secondary">Terakhir {{ \App\Helpers\AttendanceClock::toLocal($device->last_log_at)->format('d M H:i') }}</div>@endif
              </td>
              <td>
                @if($device->isPending())<span class="badge badge-soft-warning">Baru terdeteksi</span>
                @else<span class="badge {{ $device->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $device->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                  @if($device->is_active && ! $device->hasIpRestriction())<div><span class="badge badge-soft-{{ $device->blockedForMissingIpRestriction() ? 'danger' : 'warning' }}">{{ $device->blockedForMissingIpRestriction() ? 'Ditolak: IP belum dibatasi' : 'IP belum dibatasi' }}</span></div>@endif
                @endif
              </td>
              <td class="text-end text-nowrap">
                @if($canManage)
                  <a href="{{ route('admin.academic.fingerprint.devices.edit', $device) }}" class="btn btn-sm btn-outline-primary">{{ $device->isPending() ? 'Atur & aktifkan' : 'Ubah' }}</a>
                  @unless($device->isPending())
                    <form method="POST" action="{{ route('admin.academic.fingerprint.devices.toggle', $device) }}" class="d-inline">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $device->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                  @endunless
                  @unless($device->is_active)
                    <form method="POST" action="{{ route('admin.academic.fingerprint.devices.destroy', $device) }}" class="d-inline" onsubmit="return confirm('Hapus mesin ini beserta seluruh log ketukannya? Absensi yang sudah tercatat tidak ikut terhapus.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form>
                  @endunless
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="empty-state py-5">Belum ada mesin. Arahkan mesin ke alamat server di bawah, atau tambahkan manual dengan nomor serinya.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
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
