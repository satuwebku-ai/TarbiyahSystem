{{-- Dropdown unit berurutan (pohon) dengan pencarian. Butuh @include('partials.admin.select2-assets').
     Param: $unitOptions (dari EmployeeAccess::unitOptions), $selected, $id, $name. --}}
@php
  $id = $id ?? 'organizational_unit_id';
  $name = $name ?? 'organizational_unit_id';
  $selected = $selected ?? null;
@endphp
<select id="{{ $id }}" name="{{ $name }}" required data-placeholder="Cari atau pilih unit…"
        class="form-select js-unit-select @error($name) is-invalid @enderror">
  <option value=""></option>
  @foreach ($unitOptions as $option)
    <option value="{{ $option['id'] }}"
            data-name="{{ $option['name'] }}" data-code="{{ $option['code'] }}"
            data-depth="{{ $option['depth'] }}" data-path="{{ $option['path'] }}"
            @selected((string) $selected === (string) $option['id'])>{{ $option['name'] }} · {{ $option['code'] }}</option>
  @endforeach
</select>
