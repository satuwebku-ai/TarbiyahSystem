@extends('layouts.auth')
@section('title', 'Lupa Kata Sandi')
@section('subtitle', 'Portal Pegawai / Guru')
@section('content')
  @include('partials.forgot-form', ['action' => route('pegawai.password.email'), 'login' => route('pegawai.login')])
@endsection
