@extends('admin._layout')
@section('title', $definition['title'])
@section('parent', 'Laporan')
@section('actions')
  <a href="{{ route('admin.reports.index', request()->query()) }}" data-ajax-qs class="btn btn-light btn-sm">Dashboard</a>
  @can('reports.export')
    <a href="{{ route('admin.reports.export', ['report' => $type] + request()->query()) }}" data-ajax-qs class="btn btn-outline-primary btn-sm">Ekspor CSV (Excel)</a>
    <a href="{{ route('admin.reports.print', ['report' => $type] + request()->query()) }}" target="_blank" rel="noopener" data-ajax-qs class="btn btn-primary btn-sm">Cetak / PDF</a>
  @endcan
@endsection
@section('content')
  @include('admin.reports._filter', [
    'action' => route('admin.reports.show', $type),
    'usesPeriod' => $definition['uses_period'],
  ])

  <div data-ajax-region>
  @fragment('table')
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
      <div class="fw-semibold">{{ $definition['title'] }}</div>
      <div class="small text-secondary">
        @if($definition['uses_period']) {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · @endif
        {{ $definition['description'] }}
      </div>
    </div>
    @include('admin.reports._table', ['data' => $data])
    @if($data['note'])
      <div class="card-footer bg-transparent small text-secondary">{{ $data['note'] }}</div>
    @endif
  </div>
  @endfragment
  </div>
@endsection
