@extends('admin._layout')
@section('title', 'Profil Saya')
@section('content')
  @php $me = auth()->user(); @endphp
  <div class="card border-0 shadow-sm">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <span class="avatar avatar-lg">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($me->name, 0, 1)) }}</span>
      <div class="flex-grow-1">
        <div class="fw-bold fs-5">{{ $me->name }}</div>
        <div class="text-secondary small">{{ $me->email }}</div>
      </div>
      <div>@foreach($me->roles as $r)<span class="badge badge-soft-primary">{{ $r->label }}</span> @endforeach</div>
    </div>
  </div>
  @include('partials.profile-forms', ['profileAction' => route('admin.profile.update'), 'passwordAction' => route('admin.profile.password')])
@endsection
