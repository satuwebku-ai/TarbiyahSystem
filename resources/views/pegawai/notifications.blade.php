@extends('pegawai._layout')
@section('title', 'Notifikasi')
@section('title_visible', 'Notifikasi')
@section('subtitle', 'Pemberitahuan dan pengingat untuk akun Anda.')
@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <ul class="nav nav-pills">
      <li class="nav-item"><a class="nav-link {{ $filter === 'all' ? 'active' : '' }}" href="{{ route('pegawai.notifications.index') }}">Semua</a></li>
      <li class="nav-item"><a class="nav-link {{ $filter === 'unread' ? 'active' : '' }}" href="{{ route('pegawai.notifications.index', ['filter' => 'unread']) }}">Belum dibaca</a></li>
    </ul>
    @if ($unreadCount)
      <form method="POST" action="{{ route('pegawai.notifications.read-all') }}">@csrf
        <button class="btn btn-sm btn-outline-primary" type="submit">Tandai semua dibaca ({{ $unreadCount }})</button>
      </form>
    @endif
  </div>
  @include('partials.notification-list')
@endsection
