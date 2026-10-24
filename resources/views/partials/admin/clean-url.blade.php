{{-- Membersihkan query string dari address bar setelah halaman terbuka (?admin_id=3, ?tab=x, dst.).
     Halaman sudah dirender dengan nilai dari query tersebut; yang hilang hanya kemampuan refresh/bookmark dengan filter itu.
     Pakai: @push('scripts') @include('partials.admin.clean-url') @endpush --}}
@once
<script>
  (function () {
    if (location.search && window.history && history.replaceState) {
      history.replaceState(history.state, '', location.pathname + location.hash);
    }
  })();
</script>
@endonce
