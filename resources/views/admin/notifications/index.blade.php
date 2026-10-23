@extends('admin._layout')
@section('title', 'Notifikasi')
@section('actions')
  @if ($unreadCount)
    <form method="POST" action="{{ route('admin.notifications.read-all') }}">@csrf
      <button class="btn btn-sm btn-outline-primary" type="submit">Tandai semua dibaca ({{ $unreadCount }})</button>
    </form>
  @endif
@endsection
@section('content')
  <ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link {{ $filter === 'all' ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">Semua</a></li>
    <li class="nav-item"><a class="nav-link {{ $filter === 'unread' ? 'active' : '' }}" href="{{ route('admin.notifications.index', ['filter' => 'unread']) }}">Belum dibaca</a></li>
  </ul>
  @include('partials.notification-list')
@endsection
