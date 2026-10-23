{{-- Alur pengisian struktur: Jenis unit -> Unit -> Jabatan -> Penugasan. Param: $active (key langkah halaman ini), $compact --}}
@php($compact = $compact ?? false)
@php($allDone = collect($setupSteps)->every(fn ($s) => $s['state'] === 'done'))
@if (! ($compact && $allDone))
  <div class="card border-0 shadow-sm org-steps {{ $compact ? 'org-steps-compact' : '' }}">
    <div class="card-body {{ $compact ? 'py-2 px-3' : 'p-3 p-md-4' }}">
      @unless ($compact)
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <div class="fw-semibold">Alur pengisian struktur</div>
            <div class="small text-secondary">
              {{ $allDone ? 'Semua langkah dasar sudah terisi. Tambah atau ubah data kapan saja.' : 'Isi berurutan dari kiri ke kanan. Langkah berikutnya terbuka otomatis.' }}
            </div>
          </div>
        </div>
      @endunless
      <ol class="org-steps-list list-unstyled mb-0">
        @foreach ($setupSteps as $step)
          @php($isHere = ($active ?? null) === $step['key'])
          <li class="org-step org-step-{{ $step['state'] }} {{ $isHere ? 'is-here' : '' }}" @if ($isHere) aria-current="step" @endif>
            <span class="org-step-dot">
              @if ($step['state'] === 'done') <x-icon name="check" size="14"/> @else {{ $step['number'] }} @endif
            </span>
            <div class="org-step-body">
              <div class="org-step-title">{{ $step['label'] }}
                @if ($step['state'] === 'done')<span class="org-step-count">{{ number_format($step['count'], 0, ',', '.') }}</span>@endif
              </div>
              @unless ($compact)
                <div class="org-step-hint">
                  @if ($step['state'] === 'locked') {{ $step['needs'] }}
                  @elseif ($step['is_next']) <strong>Langkah berikutnya</strong>
                  @else {{ $step['hint'] }} @endif
                </div>
              @endunless
              @if ($step['state'] !== 'locked' && ! $isHere)
                @can($step['ability'])
                  <a class="stretched-link small text-decoration-none" href="{{ route($step['is_next'] ? $step['route'] : $step['list']) }}" @if($compact) aria-label="{{ $step['label'] }}" @endif>
                    @unless ($compact){{ $step['is_next'] ? 'Mulai isi' : 'Kelola' }} &rarr;@endunless
                  </a>
                @endcan
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
      .org-steps-list { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; counter-reset:none; }
      .org-step { position:relative; display:flex; gap:.7rem; align-items:flex-start; padding:.75rem; border:1px solid rgba(128,128,128,.25); border-radius:.75rem; }
      .org-step-dot { flex:0 0 auto; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; font-size:.8rem; font-weight:600; background:rgba(128,128,128,.18); color:inherit; }
      .org-step-title { font-weight:600; line-height:1.3; }
      .org-step-count { margin-left:.35rem; font-size:.72rem; font-weight:600; padding:.05rem .45rem; border-radius:999px; background:rgba(25,135,84,.15); color:#198754; }
      .org-step-hint { font-size:.8rem; color:var(--bs-secondary-color); }
      .org-step-done .org-step-dot { background:#198754; color:#fff; }
      .org-step-current { border-color:rgba(var(--bs-primary-rgb),.6); background:rgba(var(--bs-primary-rgb),.06); }
      .org-step-current .org-step-dot { background:var(--bs-primary); color:#fff; }
      .org-step-locked { opacity:.55; }
      .org-step.is-here { box-shadow:0 0 0 2px rgba(var(--bs-primary-rgb),.35); }
      .org-steps-compact .org-step { padding:.4rem .6rem; align-items:center; }
      .org-steps-compact .org-step-dot { width:22px; height:22px; font-size:.7rem; }
      @media (max-width:767.98px) { .org-steps-list { grid-template-columns:1fr 1fr; } }
      @media (max-width:420px) { .org-steps-list { grid-template-columns:1fr; } }
    </style>
    @endpush
  @endonce
@endif
