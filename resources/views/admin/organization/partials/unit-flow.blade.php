{{-- Alur pengisian unit organisasi + jumlah unit yang sudah diinput per jenis. Param: $flow, opsional $filters/$view. --}}
@php
  $flow = $flow ?? [];
  $filledCount = collect($flow)->where('state', 'done')->count();
  $totalUnits = collect($flow)->sum('count');
@endphp
@if ($flow)
  <div class="card border-0 shadow-sm mb-3" id="orgUnitFlow">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
          <div class="fw-semibold">Alur pengisian unit organisasi</div>
          <div class="small text-secondary">
            Isi berurutan dari kiri ke kanan: induk lebih dulu, lalu turunannya.
            <strong>{{ $filledCount }}</strong> dari {{ count($flow) }} tingkat sudah terisi &middot; <strong>{{ number_format($totalUnits, 0, ',', '.') }}</strong> unit.
          </div>
        </div>
      </div>
      <ol class="uf-list list-unstyled mb-0">
        @foreach ($flow as $step)
          <li class="uf-step uf-{{ $step['state'] }}" @if ($step['is_next']) aria-current="step" @endif>
            <div class="d-flex align-items-start gap-2">
              <span class="uf-dot">
                @if ($step['state'] === 'done') <x-icon name="check" size="14"/> @else {{ $step['number'] }} @endif
              </span>
              <div class="flex-grow-1 min-w-0">
                <div class="uf-title">{{ $step['label'] }}</div>
                <div class="uf-hint">
                  @if ($step['state'] === 'locked') {{ $step['needs'] }}
                  @elseif ($step['is_next']) <strong>Langkah berikutnya.</strong> {{ $step['hint'] }}
                  @else {{ $step['hint'] }} @endif
                </div>
              </div>
              <div class="uf-count text-end" title="Jumlah {{ $step['name'] }} yang sudah diinput">
                <div class="uf-num">{{ number_format($step['count'], 0, ',', '.') }}</div>
                <div class="uf-unit">unit</div>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-2">
              @can('organization.units.manage')
                @if ($step['can_add'])
                  <button type="button" class="btn btn-sm {{ $step['is_next'] ? 'btn-primary' : 'btn-outline-primary' }}" data-bs-toggle="modal" data-bs-target="#orgAddModal" data-type-id="{{ $step['type_id'] }}">
                    <x-icon name="plus" size="13" class="me-1"/>Tambah {{ $step['label'] }}
                  </button>
                @endif
              @endcan
              @if ($step['count'] > 0)
                <a class="btn btn-sm btn-link text-decoration-none px-1" href="{{ route('admin.organization.units.index', ['view' => $view ?? 'tree', 'type_id' => $step['type_id']]) }}">Lihat</a>
              @endif
            </div>
          </li>
        @endforeach
      </ol>
    </div>
  </div>
  @once
    @push('scripts')
    <style>
      .uf-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:.75rem; }
      .uf-step { position:relative; padding:.75rem; border:1px solid rgba(128,128,128,.25); border-radius:.75rem; }
      .uf-dot { flex:0 0 auto; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; font-size:.8rem; font-weight:600; background:rgba(128,128,128,.18); }
      .uf-title { font-weight:600; line-height:1.3; }
      .uf-hint { font-size:.8rem; color:var(--bs-secondary-color); }
      .uf-num { font-size:1.35rem; font-weight:700; line-height:1; }
      .uf-unit { font-size:.7rem; color:var(--bs-secondary-color); }
      .uf-done { border-color:rgba(25,135,84,.45); }
      .uf-done .uf-dot { background:#198754; color:#fff; }
      .uf-done .uf-num { color:#198754; }
      .uf-current { border-color:rgba(var(--bs-primary-rgb),.6); background:rgba(var(--bs-primary-rgb),.06); }
      .uf-current .uf-dot { background:var(--bs-primary); color:#fff; }
      .uf-locked { opacity:.6; }
      .uf-step:not(:last-child)::after { content:"\203A"; position:absolute; right:-.62rem; top:50%; transform:translateY(-50%); z-index:1; color:var(--bs-secondary-color); font-size:1.2rem; line-height:1; }
      @media (max-width:991.98px) { .uf-step::after { display:none; } }
    </style>
    @endpush
  @endonce
@endif
