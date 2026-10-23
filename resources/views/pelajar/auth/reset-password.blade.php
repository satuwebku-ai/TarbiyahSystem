@extends('layouts.auth')
@section('title', 'Reset Kata Sandi')
@section('subtitle', 'Area Pelajar')
@section('content')
  @include('partials.reset-form', ['action' => route('pelajar.password.store'), 'token' => $token, 'email' => $email])
@endsection
