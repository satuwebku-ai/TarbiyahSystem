@if (session('success') || session('status'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') ?? session('status') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
