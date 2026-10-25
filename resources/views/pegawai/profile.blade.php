@extends('pegawai._layout')
@section('title', 'Profil & Keamanan')
@section('title_visible', 'Profil & Keamanan')
@section('subtitle', 'Kelola data akun dan kata sandi Anda. Data kepegawaian diubah oleh pengelola.')
@section('content')
  @include('partials.profile-forms', ['profileAction' => route('pegawai.profile.update'), 'passwordAction' => route('pegawai.profile.password')])
  @include('partials.two-factor-card', ['user' => auth()->user(), 'prefix' => 'pegawai'])
@endsection
