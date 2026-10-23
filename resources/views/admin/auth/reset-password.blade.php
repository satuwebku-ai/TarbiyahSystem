@extends('layouts.auth')
@section('title', 'Reset Kata Sandi')
@section('subtitle', 'Panel Pengelola')
@section('content')
  @include('partials.reset-form', ['action' => route('admin.password.store'), 'token' => $token, 'email' => $email])
@endsection
