@php
  $labels = [
            'admin.login_success' => 'Login berhasil',
            'admin.logout' => 'Logout',
            'admin.login_failed' => 'Login gagal',
            'admin.login_throttled' => 'Login diblokir sementara',
            'pegawai.login_failed' => 'Login portal pegawai gagal',
            'pegawai.login_throttled' => 'Login portal pegawai diblokir sementara',
            'pegawai.login_success' => 'Login portal pegawai',
            'pegawai.logout' => 'Logout portal pegawai',
            'pegawai.session_expired' => 'Sesi portal pegawai berakhir',
            'pegawai.session_revoked' => 'Sesi portal pegawai dihentikan',
            'admin.password_changed' => 'Password diubah',
            'admin.password_reset' => 'Password direset',
            'admin.created' => 'Admin dibuat',
            'admin.updated' => 'Admin diperbarui',
            'admin.deleted' => 'Admin dihapus',
            'admin.inactive_session_revoked' => 'Sesi admin nonaktif dihentikan',
            'admin.session_expired' => 'Sesi berakhir karena tidak aktif',
            'role.created' => 'Role dibuat',
            'role.updated' => 'Role diperbarui',
            'role.deleted' => 'Role dihapus',
            'security.settings_updated' => 'Kebijakan keamanan diperbarui',
            'employee.created' => 'Pegawai didaftarkan',
            'employee.updated' => 'Data pegawai diperbarui',
            'employee.status_changed' => 'Status pegawai diubah',
            'employee.assignment_created' => 'Penempatan pegawai ditambahkan',
            'employee.assignment_updated' => 'Penempatan pegawai diperbarui',
            'employee.assignment_ended' => 'Penempatan pegawai diakhiri',
            'employee.account_linked' => 'Akun dihubungkan ke pegawai',
            'employee.account_unlinked' => 'Akun dilepas dari pegawai',
            'employee.account_deactivated' => 'Akun pegawai dinonaktifkan',
            'employee.document_uploaded' => 'Dokumen pegawai diunggah',
            'employee.document_deleted' => 'Dokumen pegawai dihapus',
            'attendance.correction_approved' => 'Koreksi absensi disetujui',
            'attendance.correction_rejected' => 'Koreksi absensi ditolak',
            'attendance.entry_created' => 'Izin/sakit/cuti dicatat',
            'attendance.entry_deleted' => 'Catatan izin/sakit/cuti dihapus',
            'attendance.policy_created' => 'Kebijakan absensi dibuat',
            'attendance.policy_updated' => 'Kebijakan absensi diperbarui',
            'attendance.holiday_created' => 'Hari libur ditambahkan',
            'attendance.holiday_deleted' => 'Hari libur dihapus',
          ];
@endphp
@switch($col)
  @case('event')
    <div class="fw-semibold">{{ $labels[$entry->event] ?? $entry->event }}</div>
    @if(!empty($entry->details['changed_fields']))
      <div class="small text-secondary">Bidang: {{ implode(', ', $entry->details['changed_fields']) }}</div>
    @endif
    @if(!empty($entry->details['password_changed']))
      <div class="small text-secondary">Password akun admin ikut diperbarui.</div>
    @endif
    @break
  @case('actor_name')
    {{ $entry->actor_name ?: (!empty($entry->details['identifier']) ? $entry->details['identifier'].' (belum login)' : 'Sistem') }}
    @break
@endswitch
