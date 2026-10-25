@extends('layouts.auth')
@section('title', 'Login Pegawai / Guru')
@section('subtitle', 'Portal Pegawai / Guru')
@section('content')
  @include('partials.login-form', [
    'field'  => 'username',
    'label'  => 'Username',
    'action' => route('pegawai.login.store'),
    'forgot' => route('pegawai.password.request'),
  ])
@endsection
