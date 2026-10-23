{{-- Pilihan KOMWIL → KOMDA → Sekolah, otomatis menyesuaikan cakupan pengelola.
     Tingkat yang hanya punya satu pilihan (mis. KOMDA hanya melihat daerahnya, kepala sekolah hanya sekolahnya)
     tidak ditampilkan; sekolah tunggal dikirim sebagai input tersembunyi.
     Param : $scope (SchoolScope::options), $selected ['komwil','komda','school'], $cols (kelas kolom, opsional),
             $required (sekolah wajib), $allLabel (teks "semua sekolah").
     Butuh @include('partials.admin.select2-assets') dan partials.admin.school-classroom-sync (di @push scripts). --}}
@php
  $cols = array_merge(['komwil' => 'col-md-3', 'komda' => 'col-md-3', 'school' => 'col-md-6'], $cols ?? []);
  $sel = $selected ?? [];
  $required = $required ?? false;
  $allLabel = $allLabel ?? 'Semua sekolah dalam cakupan';
  $same = fn ($a, $b) => (string) $a !== '' && (string) $a === (string) $b;
@endphp

@if($scope['komwils']->count() > 1)
  <div class="{{ $cols['komwil'] }}">
    <label for="komwil_id" class="form-label small mb-1">KOMWIL</label>
    <select id="komwil_id" name="komwil_id" class="form-select">
      <option value="">Semua KOMWIL</option>
      @foreach($scope['komwils'] as $unit)
        <option value="{{ $unit->id }}" @selected($same($sel['komwil'] ?? '', $unit->id))>{{ $unit->name }}</option>
      @endforeach
    </select>
  </div>
@endif

@if($scope['komdas']->count() > 1)
  <div class="{{ $cols['komda'] }}">
    <label for="komda_id" class="form-label small mb-1">KOMDA</label>
    <select id="komda_id" name="komda_id" class="form-select">
      <option value="">Semua KOMDA</option>
      @foreach($scope['komdas'] as $unit)
        <option value="{{ $unit->id }}" data-komwil-id="{{ $unit->parent_id }}" @selected($same($sel['komda'] ?? '', $unit->id))>{{ $unit->name }}</option>
      @endforeach
    </select>
  </div>
@endif

@if($scope['schools']->count() === 1)
  <input type="hidden" id="school_id" name="school_id" value="{{ $scope['schools']->first()['id'] }}">
@else
  <div class="{{ $cols['school'] }}">
    <label for="school_id" class="form-label small mb-1">Sekolah</label>
    <select id="school_id" name="school_id" class="form-select" @required($required)>
      <option value="">{{ $required ? 'Pilih sekolah' : $allLabel }}</option>
      @foreach($scope['schools'] as $school)
        <option value="{{ $school['id'] }}" data-komda-id="{{ $school['komda_id'] }}" data-komwil-id="{{ $school['komwil_id'] }}"
                @selected($same($sel['school'] ?? '', $school['id']))>{{ $school['name'] }}{{ $school['level'] !== '' ? ' · '.$school['level'] : '' }}</option>
      @endforeach
    </select>
  </div>
@endif
