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
<div class="table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr>
        @foreach($data['columns'] as $column)
          <th class="{{ $column['type'] === 'text' ? '' : 'text-end' }}">{{ $column['label'] }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($data['rows'] as $row)
        <tr>
          @foreach($data['columns'] as $column)
            <td class="{{ $column['type'] === 'text' ? '' : 'text-end' }}">{{ $fmt($column, $row[$column['key']] ?? null) }}</td>
          @endforeach
        </tr>
      @empty
        <tr><td colspan="{{ count($data['columns']) }}" class="text-center text-secondary py-5">Belum ada data pada filter ini.</td></tr>
      @endforelse
    </tbody>
    @if($totals)
      <tfoot>
        <tr class="fw-semibold table-light">
          @foreach($data['columns'] as $column)
            <td class="{{ $column['type'] === 'text' ? '' : 'text-end' }}">{{ $fmt($column, $totals[$column['key']] ?? null) }}</td>
          @endforeach
        </tr>
      </tfoot>
    @endif
  </table>
</div>
