@extends('layouts.auth')
@section('title', 'Login Pengelola')
@section('subtitle', 'Panel Pengelola')
@section('content')
  @include('partials.login-form', ['action' => route('admin.login.store'), 'field' => 'username', 'label' => 'Username', 'forgot' => route('admin.password.request')])
  @if(app()->environment(['local', 'development', 'testing']) && !\App\Models\Admin::query()->exists())
    <div class="text-center mt-3 small">
      <a href="{{ route('admin.setup') }}" class="text-decoration-none">Buat akun Super Admin pertama</a>
    </div>
  @endif
  @include('partials.demo-accounts', ['guard' => 'admin'])
@endsection
