@extends('admin._layout')
@section('title', 'Akun Admin per Unit Dibuat')
@section('parent', 'Akun Pengelola')
@section('parent_url', route('admin.admins.index'))
@section('content')
  @if(count($created) === 0)
    <div class="alert alert-info">Tidak ada akun baru yang dibuat (semua unit sudah punya akun, atau tidak ada yang memenuhi syarat).</div>
    <a href="{{ route('admin.unit-admins.index') }}" class="btn btn-light">Kembali</a>
  @else
    <div class="alert alert-warning">
      <strong>{{ count($created) }} akun dibuat. Kata sandi di bawah hanya ditampilkan SEKALI</strong> dan tidak dapat dilihat lagi setelah halaman ini ditutup.
      Unduh CSV atau salin sekarang, simpan di tempat aman, lalu serahkan ke tiap pemegang akun lewat jalur yang aman.
      Minta mereka mengganti kata sandi, mengisi email asli{{ collect($created)->contains(fn ($c) => str_ends_with($c['email'], '@'.\App\Services\UnitAdminProvisioner::DEFAULT_EMAIL_DOMAIN)) ? ' (email sementara .invalid tidak bisa menerima surel)' : '' }}, dan mengaktifkan 2FA.
      @if(! $created[0]['active'])<div class="mt-1">Akun dibuat <strong>nonaktif</strong>: aktifkan dari menu Akun Pengelola saat diserahkan.</div>@endif
    </div>
    <div class="mb-3"><button type="button" class="btn btn-primary" id="downloadCsv">Unduh CSV</button> <a href="{{ route('admin.admins.index') }}" class="btn btn-light">Ke daftar akun</a></div>
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Unit</th><th>Peran</th><th>Username</th><th>Email</th><th>Kata sandi sementara</th></tr></thead>
          <tbody>
            @foreach($created as $c)
              <tr>
                <td>{{ $c['unit']->name }}<div class="small text-secondary">{{ $c['unit']->code }}</div></td>
                <td>{{ $c['role']->label }}</td>
                <td><code>{{ $c['username'] }}</code></td>
                <td class="small">{{ $c['email'] }}</td>
                <td><code class="user-select-all">{{ $c['password'] }}</code></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @php
      $csvRows = collect($created)->map(fn ($c) => [
        $c['unit']->code, $c['unit']->name, $c['unit']->type->slug, $c['role']->name, $c['name'],
        $c['username'], $c['email'], $c['password'], $c['active'] ? 'aktif' : 'nonaktif',
      ])->all();
    @endphp
    @push('scripts')
      <script>
        (function () {
          const rows = @json($csvRows);
          const header = ['kode_unit', 'unit', 'jenis', 'peran', 'nama', 'username', 'email', 'password', 'status_akun'];
          // Awalan ' mencegah rumus spreadsheet (CSV injection) bila dibuka di Excel.
          const cell = v => { v = String(v); if (/^[=+\-@]/.test(v)) v = "'" + v; return '"' + v.replace(/"/g, '""') + '"'; };
          document.getElementById('downloadCsv').addEventListener('click', function () {
            const csv = [header, ...rows].map(r => r.map(cell).join(',')).join('\r\n');
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' }));
            a.download = 'akun-admin-per-unit.csv';
            a.click();
            URL.revokeObjectURL(a.href);
          });
        })();
      </script>
    @endpush
  @endif
@endsection
