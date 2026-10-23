@extends('admin._layout')
@section('title', $employee->name)
@section('parent', 'Data Pegawai')
@section('parent_url', route('admin.employees.index'))
@section('actions')
  @can('employees.update')<a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-outline-primary btn-sm"><x-icon name="edit" size="15" class="me-1"/>Ubah data</a>@endcan
@endsection
@section('content')
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Data pokok</div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-5">Nomor pegawai</dt><dd class="col-7">{{ $employee->employee_number }}</dd>
            <dt class="col-5">Jenis</dt><dd class="col-7">{{ $employee->typeLabel() }}</dd>
            <dt class="col-5">Status</dt><dd class="col-7"><span class="badge {{ $employee->status->badge() }}">{{ $employee->status->label() }}</span></dd>
            <dt class="col-5">Email</dt><dd class="col-7">{{ $employee->email ?: '—' }}</dd>
            <dt class="col-5">Telepon</dt><dd class="col-7">{{ $employee->phone ?: '—' }}</dd>
            <dt class="col-5">Tanggal masuk</dt><dd class="col-7">{{ $employee->hire_date?->format('d M Y') ?? '—' }}</dd>
            <dt class="col-5">Alamat</dt><dd class="col-7">{{ $employee->address ?: '—' }}</dd>
            <dt class="col-5">Akun terhubung</dt><dd class="col-7">{{ $employee->user ? $employee->user->name.' ('.$employee->user->username.')' : 'Belum ada' }}</dd>
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <span class="fw-semibold">Penempatan</span>
          @can('employees.assignments.manage')<a href="{{ route('admin.employees.assignments.create', $employee) }}" class="small text-decoration-none">+ Tambah</a>@endcan
        </div>
        <div class="card-body p-0">
          @forelse($employee->assignments as $assignment)
            <div class="px-3 py-2 border-bottom d-flex justify-content-between gap-3">
              <div>
                <div class="fw-semibold">{{ $assignment->unit?->name }} @if($assignment->is_primary)<span class="badge badge-soft-primary">Utama</span>@endif</div>
                <div class="small text-secondary">{{ $assignment->position?->name ?? 'Tanpa jabatan' }} · {{ $assignment->start_date->format('d M Y') }} — {{ $assignment->end_date?->format('d M Y') ?? 'sekarang' }}</div>
              </div>
              @can('employees.assignments.manage')
                <div class="text-nowrap">
                  <a href="{{ route('admin.employees.assignments.edit', [$employee, $assignment]) }}" class="btn btn-sm btn-outline-primary">Ubah</a>
                  @if(! $assignment->end_date)
                    <form method="POST" action="{{ route('admin.employees.assignments.end', [$employee, $assignment]) }}" class="d-inline">
                      @csrf @method('PATCH')
                      <input type="hidden" name="end_date" value="{{ max(today()->toDateString(), $assignment->start_date->toDateString()) }}">
                      <button class="btn btn-sm btn-outline-warning" type="submit">Akhiri</button>
                    </form>
                  @endif
                </div>
              @endcan
            </div>
          @empty
            <div class="empty-state py-4">Belum ada penempatan.</div>
          @endforelse
        </div>
      </div>
    </div>

    @can('employees.status')
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Ubah status kepegawaian</div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.employees.status.store', $employee) }}" class="row g-2">
            @csrf
            <div class="col-md-6">
              <label for="status" class="form-label small mb-1">Status baru</label>
              <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                @foreach($statuses as $s)<option value="{{ $s->value }}" @selected($employee->status === $s)>{{ $s->label() }}</option>@endforeach
              </select>
              @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label for="effective_date" class="form-label small mb-1">Berlaku mulai</label>
              <input id="effective_date" type="date" name="effective_date" value="{{ old('effective_date', today()->toDateString()) }}" class="form-control @error('effective_date') is-invalid @enderror">
              @error('effective_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12"><input name="reason" maxlength="500" placeholder="Alasan (opsional)" value="{{ old('reason') }}" class="form-control"></div>
            <div class="col-12 small text-secondary">Status pensiun/mengundurkan diri menutup penempatan yang masih berjalan dan mencabut akses portal pegawai.</div>
            <div class="col-12 text-end"><button class="btn btn-primary btn-sm" type="submit">Simpan status</button></div>
          </form>
        </div>
      </div>
    </div>
    @endcan

    @can('employees.link_account')
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Akun portal pegawai</div>
        <div class="card-body">
          <form method="POST" action="{{ route('admin.employees.account.update', $employee) }}" class="row g-2">
            @csrf @method('PUT')
            <div class="col-12">
              <select name="user_id" class="form-select @error('user_id') is-invalid @enderror">
                <option value="">Tidak terhubung</option>
                @foreach($linkableUsers as $u)<option value="{{ $u->id }}" @selected(old('user_id', $employee->user_id) == $u->id)>{{ $u->name }} ({{ $u->username }})</option>@endforeach
              </select>
              @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 text-end"><button class="btn btn-primary btn-sm" type="submit">Simpan</button></div>
          </form>

          @unless($employee->user_id)
          <hr>
          <form method="POST" action="{{ route('admin.employees.account.store', $employee) }}" class="row g-2">
            @csrf
            <div class="col-12 small fw-semibold">Atau buat akun baru</div>
            <div class="col-md-6"><input name="username" value="{{ old('username') }}" class="form-control form-control-sm @error('username') is-invalid @enderror" placeholder="Username" required>@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-sm @error('email') is-invalid @enderror" placeholder="Email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><input type="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror" placeholder="Kata sandi" required autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><input type="password" name="password_confirmation" class="form-control form-control-sm" placeholder="Ulangi kata sandi" required autocomplete="new-password"></div>
            <div class="col-12 text-end"><button class="btn btn-outline-primary btn-sm" type="submit">Buat &amp; hubungkan</button></div>
          </form>
          @endunless
        </div>
      </div>
    </div>
    @endcan

    @canany(['employees.documents.view', 'employees.documents.manage'])
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Dokumen</div>
        <div class="card-body p-0">
          @forelse($employee->documents as $doc)
            <div class="px-3 py-2 border-bottom d-flex justify-content-between gap-3">
              <div><div class="fw-semibold">{{ $doc->title }}</div><div class="small text-secondary">{{ $doc->original_name }} · {{ number_format($doc->size / 1024, 0, ',', '.') }} KB</div></div>
              <div class="text-nowrap">
                @can('employees.documents.view')<a href="{{ route('admin.employees.documents.download', [$employee, $doc]) }}" class="btn btn-sm btn-outline-secondary">Unduh</a>@endcan
                @can('employees.documents.manage')
                  <form method="POST" action="{{ route('admin.employees.documents.destroy', [$employee, $doc]) }}" class="d-inline" onsubmit="return confirm('Hapus dokumen ini?')">
                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                  </form>
                @endcan
              </div>
            </div>
          @empty
            <div class="empty-state py-4">Belum ada dokumen.</div>
          @endforelse
        </div>
        @can('employees.documents.manage')
          <div class="card-footer bg-transparent">
            <form method="POST" action="{{ route('admin.employees.documents.store', $employee) }}" enctype="multipart/form-data" class="row g-2">
              @csrf
              <div class="col-md-5"><input name="title" placeholder="Judul dokumen" required maxlength="160" class="form-control form-control-sm @error('title') is-invalid @enderror"></div>
              <div class="col-md-5"><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm @error('file') is-invalid @enderror"></div>
              <div class="col-md-2 d-grid"><button class="btn btn-primary btn-sm" type="submit">Unggah</button></div>
              @error('title')<div class="small text-danger">{{ $message }}</div>@enderror
              @error('file')<div class="small text-danger">{{ $message }}</div>@enderror
            </form>
          </div>
        @endcan
      </div>
    </div>
    @endcanany

    <div class="col-lg-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-transparent fw-semibold">Riwayat status</div>
        <div class="card-body p-0">
          @forelse($employee->statusHistories as $h)
            <div class="px-3 py-2 border-bottom">
              <div class="fw-semibold">{{ $h->from_status ? \App\Enums\EmployeeStatus::from($h->from_status)->label().' → ' : '' }}{{ \App\Enums\EmployeeStatus::from($h->to_status)->label() }}</div>
              <div class="small text-secondary">{{ $h->effective_date->format('d M Y') }} · {{ $h->actor?->name ?? 'Sistem' }}@if($h->reason) · {{ $h->reason }}@endif</div>
            </div>
          @empty
            <div class="empty-state py-4">Belum ada riwayat.</div>
          @endforelse
        </div>
      </div>
    </div>
  </div>
@endsection
