@extends('layouts.auth')
@section('title', 'Login Pelajar')
@section('subtitle', 'Area Pelajar')
@section('content')
  @include('partials.login-form', [
    'field'    => 'nis',
    'label'    => 'NIS (Nomor Induk Pelajar)',
    'action'   => route('pelajar.login.store'),
    'forgot'   => route('pelajar.password.request'),
    'register' => config('domains.pelajar_registration') ? route('pelajar.register') : null,
  ])
  @include('partials.demo-accounts', ['guard' => 'pelajar'])
@endsection
