@extends('admin._layout')
@section('title', 'Buat Akun Admin per Unit')
@section('parent', 'Akun Pengelola')
@section('parent_url', route('admin.admins.index'))
@section('content')
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  @php
    $labels = ['pengurus-besar' => 'Pengurus Besar', 'komwil' => 'KOMWIL', 'komda' => 'KOMDA', 'sekolah' => 'Sekolah'];
    $statusMeta = [
      'create' => ['success', 'Akan dibuat'],
      'exists' => ['secondary', 'Sudah punya akun'],
      'inactive' => ['warning', 'Unit nonaktif'],
      'role_missing' => ['danger', 'Peran belum ada (jalankan RbacSeeder)'],
    ];
    $count = fn ($s) => count(array_filter($plan, fn ($r) => $r['status'] === $s));
    $toCreate = $count('create');
  @endphp

  <div class="alert alert-info">
    Membuat <strong>satu akun admin untuk tiap unit</strong> di struktur organisasi. Peran mengikuti jenis unit
    (Pengurus Besar, Komisaris Wilayah, Komisaris Daerah, Kepala Sekolah) dan cakupan akun dikunci ke unit itu beserta turunannya.
    Unit yang sudah punya akun berperan sama dilewati, jenis unit tanpa peran bawaan (misalnya Majelis) tidak dibuatkan akun.
    Kata sandi sementara dibuat acak dan <strong>hanya ditampilkan sekali</strong> setelah akun dibuat.
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('admin.unit-admins.index') }}" class="row g-3 align-items-end">
        <div class="col-lg-6">
          <div class="form-label mb-1">Jenis unit</div>
          @foreach(['pengurus-besar', 'komwil', 'komda', 'sekolah'] as $t)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="types[]" id="t-{{ $t }}" value="{{ $t }}" @checked(in_array($t, $types, true))>
              <label class="form-check-label" for="t-{{ $t }}">{{ $labels[$t] }}</label>
            </div>
          @endforeach
        </div>
        <div class="col-lg-4">
          <label class="form-label mb-1" for="emailDomain">Domain email akun</label>
          <input type="text" class="form-control" id="emailDomain" name="email_domain" value="{{ $emailDomain }}" placeholder="{{ \App\Services\UnitAdminProvisioner::DEFAULT_EMAIL_DOMAIN }}">
          <div class="form-text">Contoh: sekolah.sch.id. Kosongkan untuk email sementara; ganti email asli dari menu Akun Pengelola.</div>
        </div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Perbarui rencana</button></div>
      </form>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-3 mb-3 small">
    <span><span class="badge text-bg-success">{{ $toCreate }}</span> akan dibuat</span>
    <span><span class="badge text-bg-secondary">{{ $count('exists') }}</span> sudah punya akun</span>
    <span><span class="badge text-bg-warning">{{ $count('inactive') }}</span> unit nonaktif</span>
    <span><span class="badge text-bg-danger">{{ $count('role_missing') }}</span> peran belum ada</span>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Unit</th><th>Jenis</th><th>Peran</th><th>Username</th><th>Email</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($plan as $row)
            <tr>
              <td>{{ $row['unit']->name }}<div class="small text-secondary">{{ $row['unit']->code }}</div></td>
              <td>{{ $labels[$row['type']] ?? $row['type'] }}</td>
              <td>{{ $row['role']?->label ?? '—' }}</td>
              <td>{{ $row['username'] ?? '—' }}</td>
              <td class="small">{{ $row['email'] ?? '—' }}</td>
              <td><span class="badge text-bg-{{ $statusMeta[$row['status']][0] }}">{{ $statusMeta[$row['status']][1] }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-secondary py-4">Tidak ada unit pada jenis yang dipilih.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($toCreate > 0)
    <form method="POST" action="{{ route('admin.unit-admins.store') }}" class="card border-0 shadow-sm">
      @csrf
      @foreach($types as $t)<input type="hidden" name="types[]" value="{{ $t }}">@endforeach
      <input type="hidden" name="email_domain" value="{{ $emailDomain }}">
      <div class="card-body">
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="inactive" value="1" id="inactive">
          <label class="form-check-label" for="inactive">Buat akun dalam keadaan <strong>nonaktif</strong> (aktifkan satu per satu saat akun diserahkan)</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm" required>
          <label class="form-check-label" for="confirm">Saya memahami kata sandi hanya ditampilkan sekali dan harus langsung disimpan di tempat aman.</label>
        </div>
        <button class="btn btn-primary" type="submit" onclick="return confirm('Buat {{ $toCreate }} akun admin?')">Buat {{ $toCreate }} akun</button>
      </div>
    </form>
  @endif
@endsection
