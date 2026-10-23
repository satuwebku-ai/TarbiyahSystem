@extends('layouts.auth')
@section('title', 'Lupa Kata Sandi')
@section('subtitle', 'Area Pelajar')
@section('content')
  @include('partials.forgot-form', ['action' => route('pelajar.password.email'), 'login' => route('pelajar.login')])
@endsection
