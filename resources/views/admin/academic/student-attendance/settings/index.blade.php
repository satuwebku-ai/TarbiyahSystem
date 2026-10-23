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
@endsection
