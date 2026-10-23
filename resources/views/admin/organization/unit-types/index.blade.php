@extends('admin._layout')
@section('title', 'Jenis Unit')
@section('actions')
  @can('organization.types.manage')
    <a href="{{ route('admin.organization.unit-types.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" size="15" class="me-1"/>Tambah jenis</a>
  @endcan
@endsection
@section('content')
  <div class="mb-3">@include('admin.organization.partials.setup-steps', ['active' => 'types', 'compact' => true])</div>
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
      <div class="fw-semibold">Jenis unit organisasi</div>
      <div class="small text-secondary">Jenis bersifat dinamis. Nonaktifkan jenis yang tidak lagi dipakai; data unit yang sudah ada tetap tersimpan.</div>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Nama</th><th>Kode</th><th>Jumlah unit</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
        <tbody>
          @forelse ($types as $type)
            <tr>
              <td><div class="fw-semibold">{{ $type->name }}</div><div class="small text-secondary">{{ $type->description ?: '—' }}</div></td>
              <td><code>{{ $type->slug }}</code></td>
              <td>{{ number_format($type->units_count, 0, ',', '.') }}</td>
              <td><span class="badge {{ $type->is_active ? 'badge-soft-success' : 'badge-soft-secondary' }}">{{ $type->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('admin.organization.unit-types.edit', $type) }}" class="btn btn-sm btn-outline-secondary"><x-icon name="edit" size="14" class="me-1"/>Ubah</a>
                <form method="POST" action="{{ route('admin.organization.unit-types.toggle', $type) }}" class="d-inline">
                  @csrf @method('PATCH')
                  <button class="btn btn-sm {{ $type->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" type="submit">
                    {{ $type->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="empty-state py-5">Belum ada jenis unit. Tambahkan jenis sesuai kebutuhan yayasan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($types->hasPages()) <div class="card-footer bg-transparent">{{ $types->links() }}</div> @endif
  </div>
@endsection
