@extends('admin._layout')
@section('title', 'Autentikasi 2 Faktor')
@section('parent', 'Pengaturan Admin')
@section('parent_url', route('admin.security.index'))
@section('actions')
  @can('security.activity')
    <a href="{{ route('admin.security.activity') }}" class="btn btn-outline-primary">
      <x-icon name="clock" size="16" class="me-1"/>Riwayat Keamanan
    </a>
  @endcan
@endsection
@section('content')
  @php $me = auth()->user(); @endphp
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Status fitur</div>
        <div class="fs-4 fw-bold">{{ $settings->two_factor_enabled ? 'Aktif' : 'Dimatikan' }}</div>
        <div class="small text-secondary">Aplikasi autentikator (TOTP) seperti Google Authenticator</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Akun pengelola ber-2FA</div>
        <div class="fs-4 fw-bold">{{ $stats['admin']['enabled'] }} <span class="fs-6 text-secondary">dari {{ $stats['admin']['total'] }} aktif</span></div>
        <div class="small text-secondary">{{ $settings->two_factor_required_admin ? 'Wajib bagi semua pengelola' : 'Pilihan masing-masing akun' }}</div>
      </div></div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Akun pegawai ber-2FA</div>
        <div class="fs-4 fw-bold">{{ $stats['pegawai']['enabled'] }} <span class="fs-6 text-secondary">dari {{ $stats['pegawai']['total'] }} aktif</span></div>
        <div class="small text-secondary">{{ $settings->two_factor_required_pegawai ? 'Wajib bagi semua pegawai' : 'Pilihan masing-masing akun' }}</div>
      </div></div>
    </div>
  </div>

  <form method="POST" action="{{ route('admin.security.two-factor.update') }}" class="card border-0 shadow-sm mb-3">
    @csrf @method('PUT')
    <div class="card-body">
      <div class="mb-3">
        <h5 class="mb-1">Kebijakan 2FA</h5>
        <p class="text-secondary small mb-0">Berlaku untuk akun pengelola dan akun pegawai. Akun pelajar tidak memakai 2FA.</p>
      </div>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="two_factor_enabled" name="two_factor_enabled" value="1"
               @checked(old('two_factor_enabled', $settings->two_factor_enabled))>
        <label class="form-check-label" for="two_factor_enabled">Aktifkan fitur 2FA</label>
        <div class="form-text">Bila dimatikan, kode 2FA tidak diminta saat login (termasuk akun yang sudah mendaftar) dan 2FA tidak bisa didaftarkan. Data pendaftaran tetap tersimpan.</div>
      </div>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="two_factor_required_admin" name="two_factor_required_admin" value="1"
               @checked(old('two_factor_required_admin', $settings->two_factor_required_admin))>
        <label class="form-check-label" for="two_factor_required_admin">Wajibkan untuk akun pengelola (admin)</label>
        <div class="form-text">Pengelola yang belum mendaftar langsung diarahkan ke Profil untuk mengaktifkan 2FA sebelum bisa memakai panel. Aktifkan 2FA akun Anda sendiri lebih dulu.</div>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="two_factor_required_pegawai" name="two_factor_required_pegawai" value="1"
               @checked(old('two_factor_required_pegawai', $settings->two_factor_required_pegawai))>
        <label class="form-check-label" for="two_factor_required_pegawai">Wajibkan untuk akun pegawai / guru</label>
        <div class="form-text">Pegawai wajib punya ponsel dengan aplikasi autentikator. Pertimbangkan dulu sebelum mewajibkan untuk semua guru.</div>
      </div>
    </div>
    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center gap-3 flex-wrap">
      <span class="small text-secondary">Perubahan kebijakan dicatat pada riwayat keamanan.</span>
      @can('security.update')
        <button class="btn btn-primary" type="submit"><x-icon name="check" size="16" class="me-1"/>Simpan kebijakan</button>
      @endcan
    </div>
  </form>

  @foreach ([['admin', 'Akun pengelola yang memakai 2FA', $enrolledAdmins], ['pegawai', 'Akun pegawai yang memakai 2FA', $enrolledUsers]] as [$type, $title, $rows])
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h6 class="mb-1">{{ $title }}</h6>
        <p class="text-secondary small">Bila pemilik akun kehilangan ponsel dan kode pemulihannya, reset 2FA agar ia bisa login dengan kata sandi lalu mendaftar ulang.</p>
        @if ($rows->isEmpty())
          <div class="text-secondary small">Belum ada.</div>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th>Nama</th><th>Username</th><th>Aktif sejak</th><th>Kode pemulihan</th><th></th></tr></thead>
              <tbody>
              @foreach ($rows as $row)
                <tr>
                  <td>{{ $row->name }}</td>
                  <td class="text-nowrap">{{ $row->username }}</td>
                  <td class="text-nowrap">{{ $row->two_factor_confirmed_at?->format('d M Y') }}</td>
                  <td>{{ $row->recoveryCodesRemaining() }} tersisa</td>
                  <td class="text-end">
                    @can('security.update')
                      @if ($type === 'admin' && ! $me->isSuperAdmin() && ($row->isSuperAdmin() || $row->hasProtectedRole()))
                        <span class="small text-secondary">Hanya Super Admin</span>
                      @else
                        <form method="POST" action="{{ route('admin.security.two-factor.reset', [$type, $row->id]) }}" class="d-inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Reset 2FA untuk '.$row->name.'?') }})">
                          @csrf @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Reset 2FA</button>
                        </form>
                      @endif
                    @endcan
                  </td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
          <div class="mt-2">{{ $rows->links() }}</div>
        @endif
      </div>
    </div>
  @endforeach

  <div class="alert alert-light border small">
    Terkunci tanpa akses? Dari server jalankan <code>php artisan security:recover --two-factor=USERNAME</code> untuk mereset 2FA sebuah akun.
  </div>
@endsection
