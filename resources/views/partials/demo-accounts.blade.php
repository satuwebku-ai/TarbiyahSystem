{{-- Tombol login satu klik untuk akun demo. Hanya tampil bila DEMO_LOGIN aktif. Param: $guard = admin|pegawai|pelajar --}}
@if (\App\Services\DemoAccounts::enabled())
  @php $demoAccounts = app(\App\Services\DemoAccounts::class)->for($guard); @endphp
  <hr class="my-4">
  <div class="small text-secondary fw-semibold mb-2">Akun demo <span class="fw-normal">(klik untuk masuk)</span></div>
  @forelse ($demoAccounts as $acc)
    <form method="POST" action="{{ route($guard.'.demo.login') }}" class="mb-2">
      @csrf
      <input type="hidden" name="account" value="{{ $acc['id'] }}">
      <button type="submit" class="btn btn-outline-secondary w-100 text-start d-flex justify-content-between align-items-center">
        <span>
          <span class="fw-semibold d-block">{{ $acc['label'] }}</span>
          <span class="small text-secondary">{{ $acc['name'] }}</span>
        </span>
        <code class="small">{{ $acc['identifier'] }}</code>
      </button>
    </form>
  @empty
    <div class="small text-secondary">Belum ada akun demo. Jalankan <code>php artisan db:seed --class=DemoSeeder</code>.</div>
  @endforelse
@endif
