@extends('pelajar._layout')
@section('title', 'Profil & Keamanan')
@section('title_visible', 'Profil & Keamanan')
@section('subtitle', 'Kelola data diri dan kata sandi akun Anda.')
@section('content')
  @include('partials.profile-forms', ['profileAction' => route('pelajar.profile.update'), 'passwordAction' => route('pelajar.profile.password'), 'showNis' => true, 'emailOptional' => true])
@endsection
