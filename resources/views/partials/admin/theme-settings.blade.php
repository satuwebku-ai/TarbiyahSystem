{{-- Panel Pengaturan Tema — id & struktur dibaca oleh assets/TarbiyahSystem/theme-color.js --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="themeSettings" aria-labelledby="themeSettingsLabel">
  <div class="offcanvas-header border-bottom">
    <h6 class="offcanvas-title mb-0" id="themeSettingsLabel">Pengaturan Tema</h6>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body">
    <div class="ts-section">
      <div class="ts-title">Mode tampilan</div>
      <div class="ts-mode">
        <button type="button" data-ts-mode="light"><x-icon name="sun"/>Terang</button>
        <button type="button" data-ts-mode="dark"><x-icon name="moon"/>Gelap</button>
      </div>
    </div>

    <div class="ts-section">
      <div class="ts-title">Warna utama</div>
      <div id="tsSwatches" class="ts-swatches"></div>
    </div>

    <div class="ts-section">
      <div class="ts-title">Warna kustom</div>
      <div class="ts-custom">
        <input type="color" id="tsPicker" value="#4f46e5" aria-label="Pilih warna">
        <input type="text" id="tsHex" class="form-control" maxlength="7" placeholder="#4F46E5" aria-label="Kode warna heksa">
      </div>
      <p id="tsHexHelp" class="ts-hint"></p>
    </div>

    <div class="ts-section">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="tsHarmony" checked>
        <label class="form-check-label" for="tsHarmony">Selaraskan semua warna komponen</label>
      </div>
      <p class="ts-hint">Status (sukses, peringatan, bahaya) dan warna pendamping ikut menyesuaikan warna utama.</p>
    </div>

    <div class="ts-section">
      <div class="ts-title">Pratinjau</div>
      <div class="ts-preview">
        <div class="ts-preview-bar"><i></i>{{ config('app.name', 'TarbiyahSystem') }}</div>
        <div class="ts-preview-body">
          <div class="ts-preview-row">
            <button type="button" class="btn btn-primary btn-sm">Primer</button>
            <button type="button" class="btn btn-outline-primary btn-sm">Outline</button>
            <span class="badge badge-soft-primary">Primer</span>
            <span class="badge badge-soft-success">Sukses</span>
            <span class="badge badge-soft-danger">Bahaya</span>
          </div>
        </div>
      </div>
    </div>

    <button type="button" id="tsReset" class="btn btn-outline-secondary w-100">Kembalikan warna bawaan</button>
  </div>
</div>
