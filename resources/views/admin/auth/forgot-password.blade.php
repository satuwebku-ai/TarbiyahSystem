@extends('layouts.auth')
@section('title', 'Lupa Kata Sandi')
@section('subtitle', 'Panel Pengelola')
@section('content')
  @include('partials.forgot-form', ['action' => route('admin.password.email'), 'login' => route('admin.login')])
@endsection
