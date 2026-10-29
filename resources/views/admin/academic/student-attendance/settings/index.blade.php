@extends('admin._layout')
@section('title', 'Pengaturan Absensi Siswa')
@section('parent', 'Absensi Siswa')
@section('parent_url', route('admin.academic.student-attendance.index'))
@section('content')
  @include('admin.academic.student-attendance.settings.summary', ['s' => $general, 'heading' => 'Pengaturan umum', 'sub' => 'Berlaku untuk semua sekolah yang tidak punya pengaturan khusus.', 'editUrl' => $canEditGeneral ? route('admin.academic.student-attendance.settings.general.edit') : null, 'badge' => $generalSaved ? null : 'Memakai nilai bawaan'])

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">Pengaturan per sekolah</div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Sekolah</th><th>Jam masuk</th><th>Hari sekolah</th><th>Fingerprint</th><th>Alpa otomatis</th><th>Sumber</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($schools as $school)
            @php($own = $custom->get($school->id))
            @php($eff = $own ?? $general)
            <tr>
              <td class="fw-semibold">{{ $school->unit?->name }}</td>
              <td class="text-nowrap">{{ $eff->start_time }} (+{{ $eff->late_tolerance_minutes }} mnt)</td>
              <td class="small">{{ $eff->dayLabels() }}</td>
              <td><span class="badge {{ $eff->fingerprint_enabled ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $eff->fingerprint_enabled ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td>{{ $eff->auto_absent_enabled ? $eff->auto_absent_time : '—' }}</td>
              <td><span class="badge {{ $own ? 'badge-soft-primary' : 'badge-soft-secondary' }}">{{ $own ? 'Khusus sekolah' : 'Mengikuti umum' }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.academic.student-attendance.settings.school.edit', $school) }}" class="btn btn-sm btn-outline-primary">{{ $own ? 'Ubah' : 'Atur khusus' }}</a>
                @if($own)
                  <form method="POST" action="{{ route('admin.academic.student-attendance.settings.school.reset', $school) }}" class="d-inline" onsubmit="return confirm('Kembalikan sekolah ini ke pengaturan umum?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary" type="submit">Kembalikan</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty-state py-5">Belum ada sekolah dalam cakupan akses Anda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if(session('node_secret'))
    @php($secret = session('node_secret'))
    <div class="alert alert-warning border shadow-sm">
      <div class="fw-semibold mb-1">Token koneksi Node — {{ $secret['school'] }}</div>
      <p class="small mb-2">Salin dan simpan sekarang. Token <strong>tidak akan ditampilkan lagi</strong>; bila hilang, buat token baru.</p>
      <div class="input-group input-group-sm mb-2">
        <span class="input-group-text">Token</span>
        <input id="node-token" class="form-control font-monospace" value="{{ $secret['token'] }}" readonly>
        <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('node-token').value); this.textContent='Tersalin'">Salin</button>
      </div>
      <div class="small text-secondary mb-1">Isi di aplikasi Node (contoh <code>.env</code>):</div>
      <pre class="small bg-body-tertiary border rounded p-2 mb-0"><code>SERVER_URL={{ $secret['url'] }}
NODE_USERNAME={{ $secret['username'] }}
NODE_PASSWORD=(password yang Anda isi)
NODE_TOKEN={{ $secret['token'] }}</code></pre>
    </div>
  @endif

  <div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent">
      <div class="fw-semibold">Koneksi Node Fingerprint</div>
      <div class="small text-secondary">Untuk sekolah yang memakai aplikasi Node fingerprint. Aplikasi mengirim <em>username</em> + <em>password</em> (Basic auth) dan header <code>X-Node-Token</code> ke <code>{{ $nodeUrl }}</code>.</div>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Sekolah</th><th>Username</th><th>Token</th><th>Status</th><th>Terakhir terhubung</th><th>Log masuk</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse($schools as $school)
            @php($node = $nodes->get($school->id))
            <tr>
              <td class="fw-semibold">{{ $school->unit?->name }}</td>
              @if($node)
                <td class="font-monospace small">{{ $node->username }}</td>
                <td class="font-monospace small text-secondary">{{ $node->token_prefix }}••••••••</td>
                <td>
                  @if(! $node->is_active)<span class="badge badge-soft-secondary">Nonaktif</span>
                  @elseif($node->isOnline())<span class="badge badge-soft-success">Daring</span>
                  @else<span class="badge badge-soft-warning">Luring</span>@endif
                </td>
                <td class="small">{{ $node->last_seen_at?->diffForHumans() ?? 'Belum pernah' }}@if($node->last_ip)<div class="text-secondary">{{ $node->last_ip }}</div>@endif</td>
                <td>{{ number_format($node->total_logs) }}</td>
              @else
                <td colspan="5" class="text-secondary small">Belum ada koneksi Node.</td>
              @endif
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.academic.student-attendance.settings.node.edit', $school) }}" class="btn btn-sm btn-outline-primary">{{ $node ? 'Ubah' : 'Buat koneksi' }}</a>
                @if($node)
                  <form method="POST" action="{{ route('admin.academic.student-attendance.settings.node.token', $school) }}" class="d-inline" onsubmit="return confirm('Buat token baru? Token lama langsung tidak berlaku dan aplikasi Node harus diperbarui.')">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Token baru</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="empty-state py-5">Belum ada sekolah dalam cakupan akses Anda.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
