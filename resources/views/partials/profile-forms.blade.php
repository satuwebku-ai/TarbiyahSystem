<div class="row g-3">
  <div class="col-lg-6">
    <form method="POST" action="{{ $profileAction }}" class="card border-0 shadow-sm h-100">
      @csrf @method('PUT')
      <div class="card-body">
        <h6 class="mb-3">Data profil</h6>
        <div class="mb-3">
          <label class="form-label">Nama</label>
          <input name="name" value="{{ old('name', $user->name) }}" required class="form-control @error('name') is-invalid @enderror">
          @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        @if (!empty($showNis))
          <div class="mb-3">
            <label class="form-label">NIS</label>
            <input value="{{ $user->nis }}" readonly class="form-control">
          </div>
        @endif
        <div class="mb-3">
          <label class="form-label">Email {!! !empty($emailOptional) ? '<span class="text-secondary">(opsional)</span>' : '' !!}</label>
          <input type="email" name="email" value="{{ old('email', $user->email) }}" @if (empty($emailOptional)) required @endif class="form-control @error('email') is-invalid @enderror">
          @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="text-secondary small">Login terakhir: {{ $user->last_login_at?->format('d M Y H:i') ?? '—' }}</div>
      </div>
      <div class="card-footer bg-transparent"><button class="btn btn-primary">Simpan profil</button></div>
    </form>
  </div>

  <div class="col-lg-6" id="password">
    <form method="POST" action="{{ $passwordAction }}" class="card border-0 shadow-sm h-100">
      @csrf @method('PUT')
      <div class="card-body">
        <h6 class="mb-3">Ubah kata sandi</h6>
        <div class="mb-3">
          <label class="form-label">Kata sandi saat ini</label>
          <input type="password" name="current_password" required autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror">
          @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
          <label class="form-label">Kata sandi baru</label>
          <input type="password" name="password" required autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
          @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
          <label class="form-label">Ulangi kata sandi baru</label>
          <input type="password" name="password_confirmation" required autocomplete="new-password" class="form-control">
        </div>
      </div>
      <div class="card-footer bg-transparent"><button class="btn btn-primary">Ubah kata sandi</button></div>
    </form>
  </div>
</div>
