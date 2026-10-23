@extends('layouts.auth')
@section('title', 'Reset Kata Sandi')
@section('subtitle', 'Portal Pegawai / Guru')
@section('content')
  @include('partials.reset-form', ['action' => route('pegawai.password.store'), 'token' => $token, 'email' => $email])
@endsection
