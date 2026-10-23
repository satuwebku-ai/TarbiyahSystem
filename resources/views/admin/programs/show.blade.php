@extends('admin._layout')
@section('title', $workProgram->title)
@section('parent', 'Program Kerja & Kegiatan')
@section('parent_url', route('admin.programs.index'))
@section('actions')
  <a href="{{ route('admin.programs.index') }}" class="btn btn-light btn-sm">Kembali</a>
  @if(in_array($workProgram->status, ['draft', 'rejected'], true))
    @can('programs.manage')<a href="{{ route('admin.programs.edit', $workProgram) }}" class="btn btn-outline-primary btn-sm">Ubah</a>@endcan
    @can('programs.submit')
      <form method="POST" action="{{ route('admin.programs.submit', $workProgram) }}" onsubmit="return confirm('Ajukan program ini untuk persetujuan?')">
        @csrf<button class="btn btn-primary btn-sm" type="submit">Ajukan persetujuan</button>
      </form>
    @endcan
  @endif
@endsection
@section('content')
  @php
    $plannedTotal = $workProgram->activities->sum(fn ($activity) => $activity->budgetItems->sum(fn ($item) => (float) $item->planned_amount));
    $realizedTotal = $workProgram->activities->sum(fn ($activity) => $activity->budgetItems->sum(fn ($item) => (float) $item->realized_amount));
  @endphp

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between gap-3">
        <div>
          <div class="small text-secondary">{{ $workProgram->code }} · {{ $workProgram->unit?->name }}</div>
          <h5 class="mt-1 mb-2">{{ $workProgram->title }}</h5>
          <div class="text-secondary">{{ $workProgram->description ?: 'Belum ada deskripsi.' }}</div>
        </div>
        <span class="badge align-self-start text-bg-{{ $workProgram->status === 'approved' ? 'success' : ($workProgram->status === 'submitted' ? 'warning' : ($workProgram->status === 'rejected' ? 'danger' : 'secondary')) }}">{{ \App\Models\WorkProgram::STATUSES[$workProgram->status] ?? $workProgram->status }}</span>
      </div>
      <hr>
      <div class="row g-3">
        <div class="col-md-3"><div class="small text-secondary">Periode</div><div class="fw-semibold">{{ $workProgram->start_date->format('d M Y') }} – {{ $workProgram->end_date->format('d M Y') }}</div></div>
        <div class="col-md-3"><div class="small text-secondary">Target / tujuan</div><div>{{ $workProgram->objectives ?: '—' }}</div></div>
        <div class="col-md-3"><div class="small text-secondary">Anggaran program</div><div class="fw-semibold">Rp {{ number_format((float) $workProgram->estimated_budget, 0, ',', '.') }}</div></div>
        <div class="col-md-3"><div class="small text-secondary">Rencana / realisasi kegiatan</div><div class="fw-semibold">Rp {{ number_format($plannedTotal, 0, ',', '.') }} / Rp {{ number_format($realizedTotal, 0, ',', '.') }}</div></div>
      </div>
    </div>
  </div>

  @if($workProgram->status === 'submitted')
    @can('programs.approve')
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <h6 class="mb-3">Tinjau program</h6>
          <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.programs.review', $workProgram) }}">
              @csrf @method('PATCH')<input type="hidden" name="decision" value="approved">
              <button class="btn btn-success" type="submit">Setujui program</button>
            </form>
            <form method="POST" action="{{ route('admin.programs.review', $workProgram) }}" class="d-flex gap-2 flex-grow-1">
              @csrf @method('PATCH')<input type="hidden" name="decision" value="rejected">
              <input name="note" class="form-control" maxlength="1000" placeholder="Catatan revisi (wajib)" required>
              <button class="btn btn-outline-danger text-nowrap" type="submit">Minta revisi</button>
            </form>
          </div>
        </div>
      </div>
    @endcan
  @endif

  @if($workProgram->approvals->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h6>Riwayat persetujuan</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Status</th><th>Diajukan</th><th>Ditinjau</th><th>Catatan</th></tr></thead>
            <tbody>
              @foreach($workProgram->approvals as $approval)
                <tr>
                  <td>{{ ucfirst($approval->status) }}</td>
                  <td>{{ $approval->requested_at?->format('d M Y H:i') ?? '—' }}</td>
                  <td>{{ $approval->reviewer?->name ?? '—' }}</td>
                  <td>{{ $approval->note ?: '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">Kegiatan ({{ $workProgram->activities->count() }})</h5>
  </div>

  @forelse($workProgram->activities as $activity)
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
          <div>
            <div class="small text-secondary">{{ $activity->code }}{{ $activity->location ? ' · '.$activity->location : '' }}</div>
            <h6 class="mb-1">{{ $activity->title }}</h6>
            <div class="small text-secondary">{{ $activity->description ?: 'Belum ada deskripsi.' }}</div>
          </div>
          <span class="badge align-self-start text-bg-{{ $activity->status === 'completed' ? 'success' : ($activity->status === 'in_progress' ? 'primary' : 'secondary') }}">{{ \App\Models\WorkActivity::STATUSES[$activity->status] ?? $activity->status }}</span>
        </div>

        @can('programs.manage')
          <div class="d-flex gap-2 mb-3">
            @if($activity->status === 'planned')
              @foreach(['in_progress' => 'Mulai kegiatan', 'cancelled' => 'Batalkan'] as $status => $label)
                <form method="POST" action="{{ route('admin.programs.activities.status', [$workProgram, $activity]) }}">
                  @csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}">
                  <button class="btn btn-sm {{ $status === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-primary' }}" type="submit">{{ $label }}</button>
                </form>
              @endforeach
            @elseif($activity->status === 'in_progress')
              @foreach(['completed' => 'Tandai selesai', 'cancelled' => 'Batalkan'] as $status => $label)
                <form method="POST" action="{{ route('admin.programs.activities.status', [$workProgram, $activity]) }}">
                  @csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}">
                  <button class="btn btn-sm {{ $status === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $label }}</button>
                </form>
              @endforeach
            @endif
          </div>
        @endcan

        <div class="row g-3">
          <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
              <h6>Anggaran</h6>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                  <thead><tr><th>Pos</th><th class="text-end">Rencana</th><th class="text-end">Realisasi</th></tr></thead>
                  <tbody>
                    @forelse($activity->budgetItems as $item)
                      <tr><td>{{ $item->name }}</td><td class="text-end">Rp {{ number_format((float) $item->planned_amount, 0, ',', '.') }}</td><td class="text-end">Rp {{ number_format((float) $item->realized_amount, 0, ',', '.') }}</td></tr>
                    @empty
                      <tr><td colspan="3" class="text-secondary">Belum ada rincian anggaran.</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
              @can('programs.manage')
                <form method="POST" action="{{ route('admin.programs.activities.budget-items.store', [$workProgram, $activity]) }}" class="row g-2">
                  @csrf
                  <div class="col-md-5"><input name="name" class="form-control form-control-sm" placeholder="Nama pos" maxlength="150" required></div>
                  <div class="col-md-3"><input name="planned_amount" type="number" min="0" step="0.01" class="form-control form-control-sm" placeholder="Rencana" required></div>
                  <div class="col-md-3"><input name="realized_amount" type="number" min="0" step="0.01" class="form-control form-control-sm" placeholder="Realisasi"></div>
                  <div class="col-md-1"><button class="btn btn-sm btn-primary w-100" type="submit">+</button></div>
                </form>
              @endcan
            </div>
          </div>

          <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
              <h6>Peserta ({{ $activity->participants->count() }})</h6>
              @if($activity->participants->isNotEmpty())
                <ul class="small mb-2">
                  @foreach($activity->participants as $participant)
                    <li>{{ $participant->employee?->name ?? $participant->participant_name }}{{ $participant->role ? ' — '.$participant->role : '' }}</li>
                  @endforeach
                </ul>
              @else
                <div class="small text-secondary mb-2">Belum ada peserta.</div>
              @endif
              @can('programs.manage')
                <form method="POST" action="{{ route('admin.programs.activities.participants.store', [$workProgram, $activity]) }}" class="row g-2">
                  @csrf
                  <div class="col-md-6"><input name="participant_name" class="form-control form-control-sm" placeholder="Nama peserta" maxlength="160"></div>
                  <div class="col-md-4"><input name="role" class="form-control form-control-sm" placeholder="Peran" maxlength="100"></div>
                  <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit">Tambah</button></div>
                </form>
              @endcan
            </div>
          </div>

          <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
              <h6>Bukti / dokumen</h6>
              @forelse($activity->documents as $document)
                <div class="small mb-1">
                  <a href="{{ route('admin.programs.activities.documents.download', [$workProgram, $activity, $document]) }}">{{ $document->title }}</a>
                  <span class="text-secondary">({{ $document->original_name }})</span>
                </div>
              @empty
                <div class="small text-secondary mb-2">Belum ada dokumen.</div>
              @endforelse
              @can('programs.manage')
                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.programs.activities.documents.store', [$workProgram, $activity]) }}" class="row g-2 mt-2">
                  @csrf
                  <div class="col-md-5"><input name="title" class="form-control form-control-sm" placeholder="Judul dokumen" maxlength="160" required></div>
                  <div class="col-md-5"><input name="file" type="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required></div>
                  <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Unggah</button></div>
                  <div class="col-12 small text-secondary">Maksimum 10 MB; berkas disimpan privat dan perlu izin untuk diunduh.</div>
                </form>
              @endcan
            </div>
          </div>

          <div class="col-lg-6">
            <div class="border rounded-3 p-3 h-100">
              <h6>Laporan & evaluasi</h6>
              @if($activity->report)
                <div class="small mb-2">
                  <span class="badge text-bg-secondary">{{ \App\Models\ActivityReport::STATUSES[$activity->report->status] ?? $activity->report->status }}</span>
                  @if($activity->report->evaluation_note)<div class="mt-1">Catatan evaluator: {{ $activity->report->evaluation_note }}</div>@endif
                </div>
              @endif

              @if(in_array($activity->status, ['in_progress', 'completed'], true))
                @if(! $activity->report || ! in_array($activity->report->status, ['submitted', 'reviewed'], true))
                  @can('programs.manage')
                    <form method="POST" action="{{ route('admin.programs.activities.report.save', [$workProgram, $activity]) }}">
                      @csrf
                      <label class="form-label small">Ringkasan pelaksanaan</label>
                      <textarea name="summary" class="form-control form-control-sm mb-2" rows="2" required>{{ old('summary', $activity->report?->summary) }}</textarea>
                      <label class="form-label small">Hasil / capaian</label>
                      <textarea name="outcomes" class="form-control form-control-sm mb-2" rows="2">{{ old('outcomes', $activity->report?->outcomes) }}</textarea>
                      <label class="form-label small">Kendala</label>
                      <textarea name="challenges" class="form-control form-control-sm mb-2" rows="2">{{ old('challenges', $activity->report?->challenges) }}</textarea>
                      <label class="form-label small">Rekomendasi</label>
                      <textarea name="recommendations" class="form-control form-control-sm mb-2" rows="2">{{ old('recommendations', $activity->report?->recommendations) }}</textarea>
                      <button class="btn btn-sm btn-outline-primary" type="submit">Simpan draft laporan</button>
                    </form>
                    @if($activity->report && in_array($activity->report->status, ['draft', 'revision_requested'], true))
                      <form method="POST" action="{{ route('admin.programs.activities.report.submit', [$workProgram, $activity]) }}" class="mt-2">
                        @csrf<button class="btn btn-sm btn-primary" type="submit">Ajukan laporan</button>
                      </form>
                    @endif
                  @endcan
                @else
                  <div class="small"><strong>Ringkasan:</strong> {{ $activity->report->summary }}</div>
                  @if($activity->report->outcomes)<div class="small mt-1"><strong>Capaian:</strong> {{ $activity->report->outcomes }}</div>@endif
                  @if($activity->report->challenges)<div class="small mt-1"><strong>Kendala:</strong> {{ $activity->report->challenges }}</div>@endif
                  @if($activity->report->recommendations)<div class="small mt-1"><strong>Rekomendasi:</strong> {{ $activity->report->recommendations }}</div>@endif
                @endif
              @else
                <div class="small text-secondary">Laporan dapat dibuat setelah kegiatan dimulai.</div>
              @endif

              @if($activity->report?->status === 'submitted')
                @can('programs.approve')
                  <div class="d-flex gap-2 mt-3">
                    <form method="POST" action="{{ route('admin.programs.activities.report.review', [$workProgram, $activity]) }}">
                      @csrf @method('PATCH')<input type="hidden" name="decision" value="reviewed">
                      <button class="btn btn-sm btn-success" type="submit">Selesaikan evaluasi</button>
                    </form>
                    <form method="POST" action="{{ route('admin.programs.activities.report.review', [$workProgram, $activity]) }}" class="d-flex gap-2 flex-grow-1">
                      @csrf @method('PATCH')<input type="hidden" name="decision" value="revision_requested">
                      <input name="evaluation_note" class="form-control form-control-sm" placeholder="Catatan perbaikan" maxlength="1000" required>
                      <button class="btn btn-sm btn-outline-danger text-nowrap" type="submit">Minta perbaikan</button>
                    </form>
                  </div>
                @endcan
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  @empty
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body text-secondary">Belum ada kegiatan pada program ini.</div>
    </div>
  @endforelse

  @if($workProgram->status === 'approved')
    @can('programs.manage')
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6>Tambah kegiatan</h6>
          <form method="POST" action="{{ route('admin.programs.activities.store', $workProgram) }}" class="row g-2">
            @csrf
            <div class="col-md-2"><input name="code" class="form-control" placeholder="Kode kegiatan" maxlength="40" required></div>
            <div class="col-md-4"><input name="title" class="form-control" placeholder="Nama kegiatan" maxlength="180" required></div>
            <div class="col-md-3"><input name="location" class="form-control" placeholder="Lokasi" maxlength="180"></div>
            <div class="col-md-3"><input name="planned_start_at" type="datetime-local" class="form-control"></div>
            <div class="col-md-3"><input name="planned_end_at" type="datetime-local" class="form-control"></div>
            <div class="col-md-7"><input name="description" class="form-control" placeholder="Deskripsi singkat"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Tambah kegiatan</button></div>
          </form>
        </div>
      </div>
    @endcan
  @endif
@endsection
