<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $definition['title'] }}</title>
  <style>
    body { font: 12px/1.5 system-ui, -apple-system, "Segoe UI", Arial, sans-serif; color: #111; margin: 24px; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .meta { color: #555; margin-bottom: 16px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #bbb; padding: 4px 6px; }
    th { background: #f0f0f0; text-align: left; }
    .num { text-align: right; }
    tfoot td { font-weight: 600; background: #f7f7f7; }
    .note { margin-top: 12px; color: #555; }
    .no-print { margin-bottom: 16px; }
    @media print { .no-print { display: none; } body { margin: 0; } }
  </style>
</head>
<body>
  <div class="no-print"><button type="button" onclick="window.print()">Cetak / simpan sebagai PDF</button></div>
  <h1>{{ $definition['title'] }}</h1>
  <div class="meta">
    @if($definition['uses_period']) Periode {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · @endif
    Unit: {{ $unitName ?? 'Semua unit dalam cakupan' }} ·
    Dibuat {{ \App\Helpers\AttendanceClock::now()->format('d M Y H:i') }} oleh {{ $admin->name }}
  </div>

  @php
    $fmt = function (array $column, $value) {
        if ($value === null || $value === '') return '–';
        return match ($column['type']) {
            'int' => number_format((int) $value, 0, ',', '.'),
            'money' => 'Rp '.number_format((float) $value, 0, ',', '.'),
            'percent' => number_format((float) $value, 1, ',', '.').'%',
            default => $value,
        };
    };
    $totals = $data['totals'] ? ['unit' => 'Total', 'school' => 'Total', 'program' => 'Total'] + $data['totals'] : null;
  @endphp
  <table>
    <thead>
      <tr>@foreach($data['columns'] as $column)<th class="{{ $column['type'] === 'text' ? '' : 'num' }}">{{ $column['label'] }}</th>@endforeach</tr>
    </thead>
    <tbody>
      @forelse($data['rows'] as $row)
        <tr>@foreach($data['columns'] as $column)<td class="{{ $column['type'] === 'text' ? '' : 'num' }}">{{ $fmt($column, $row[$column['key']] ?? null) }}</td>@endforeach</tr>
      @empty
        <tr><td colspan="{{ count($data['columns']) }}">Belum ada data pada filter ini.</td></tr>
      @endforelse
    </tbody>
    @if($totals)
      <tfoot><tr>@foreach($data['columns'] as $column)<td class="{{ $column['type'] === 'text' ? '' : 'num' }}">{{ $fmt($column, $totals[$column['key']] ?? null) }}</td>@endforeach</tr></tfoot>
    @endif
  </table>
  @if($data['note'])<div class="note">{{ $data['note'] }}</div>@endif
</body>
</html>
