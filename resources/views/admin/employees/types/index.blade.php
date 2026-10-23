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
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Nama</th><th>Kode</th><th>Jumlah pegawai</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse ($types as $type)
            <tr>
              <td><div class="fw-semibold">{{ $type->name }}</div><div class="small text-secondary">{{ $type->description ?: '—' }}</div></td>
              <td><code>{{ $type->code }}</code></td>
              <td>{{ number_format($counts[$type->code] ?? 0, 0, ',', '.') }}</td>
              <td><span class="badge {{ $type->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.employees.types.edit', $type) }}" class="btn btn-sm btn-outline-secondary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
                <form method="POST" action="{{ route('admin.employees.types.toggle', $type) }}" class="d-inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-sm {{ $type->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" type="submit">{{ $type->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty-state py-5">Belum ada jenis pegawai.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($types->hasPages()) <div class="card-footer bg-transparent">{{ $types->links() }}</div> @endif
  </div>
@endsection
