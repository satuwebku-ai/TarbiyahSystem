{{-- CAPTCHA: @include('partials.captcha', ['page' => 'login'|'forgot'|'register']). Tidak menampilkan apa pun bila CAPTCHA tidak berlaku. --}}
@php
  $captcha = app(\App\Services\Captcha::class);
  $show = $captcha->enabledFor($page);
  $external = $captcha->externalConfig();
@endphp
@if ($show)
  <div class="mb-3">
    @if ($external)
      <div class="{{ $external['widget_class'] }}" data-sitekey="{{ config('captcha.site_key') }}"></div>
      <script src="{{ $external['script'] }}" async defer></script>
    @else
      @php $challenge = $captcha->newMathChallenge(); @endphp
      <label class="form-label" for="captcha_answer">Verifikasi: berapa hasil <strong class="text-nowrap">{{ $challenge['question'] }}</strong> ?</label>
      <input type="hidden" name="captcha_token" value="{{ $challenge['token'] }}">
      <input type="text" id="captcha_answer" name="captcha_answer" inputmode="numeric" autocomplete="off" required
             class="form-control @error('captcha') is-invalid @enderror" style="max-width:9rem">
    @endif
    @error('captcha') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
  </div>
@endif
