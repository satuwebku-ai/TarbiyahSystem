<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Services\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** Menu Pengaturan Aplikasi: sebagian isi .env (nama, email, pendaftaran pelajar, fingerprint) yang aman diubah dari UI. */
class AppSettingsController extends Controller
{
    public function index(Request $request, AppSettings $settings): View
    {
        $groups = $settings->groups();
        $tab = $request->query('tab');

        return view('admin.settings.index', [
            'settings' => $settings,
            'groups' => $groups,
            'activeTab' => array_key_exists((string) $tab, $groups) ? $tab : array_key_first($groups),
        ]);
    }

    public function update(Request $request, AppSettings $settings, string $group): RedirectResponse
    {
        abort_unless($settings->group($group) !== null, 404);

        $fields = collect($settings->fields())->where('group', $group);

        // Checkbox yang tidak dicentang tidak terkirim: nilainya "mati", bukan "tidak diubah".
        $request->merge($fields->where('type', 'bool')
            ->mapWithKeys(fn ($field, $key) => [$key => $request->boolean($key)])->all());

        $validator = Validator::make(
            $request->all(),
            $fields->map(fn ($field) => $field['rules'])->all(),
            [],
            $fields->map(fn ($field) => $field['label'])->all(),
        );
        if ($validator->fails()) {
            return redirect()->route('admin.settings.index', ['tab' => $group])
                ->withErrors($validator)->withInput($request->except($fields->where('type', 'secret')->keys()->all()));
        }

        $changed = $settings->save($group, $validator->validated());

        if ($changed) {
            // Hanya nama kunci yang dicatat, tidak pernah nilainya (ada yang rahasia).
            AdminAuditLog::record('settings.updated', $request->user(), null, ['group' => $group, 'changed_keys' => $changed]);

            // Worker antrean yang sedang hidup masih memegang konfigurasi lama (mis. SMTP): minta mereka restart.
            try {
                Artisan::call('queue:restart');
            } catch (Throwable) {
                // tidak fatal: worker akan memakai pengaturan baru saat dijalankan ulang
            }
        }

        return redirect()->route('admin.settings.index', ['tab' => $group])
            ->with('success', $changed ? 'Pengaturan disimpan.' : 'Tidak ada perubahan pada pengaturan.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email', 'max:255']]);
        $back = redirect()->route('admin.settings.index', ['tab' => 'email']);

        if (config('mail.default') === 'log') {
            return $back->with('error', 'Metode pengiriman masih "Log saja", jadi email tidak benar-benar dikirim. Pilih SMTP dan simpan dulu.');
        }

        try {
            Mail::raw(
                'Ini email uji dari '.config('app.name').'. Bila Anda menerimanya, pengaturan email sudah benar.',
                fn ($message) => $message->to($data['test_email'])->subject('Email uji - '.config('app.name')),
            );
        } catch (Throwable $e) {
            return $back->with('error', 'Email uji gagal dikirim: '.Str::limit($e->getMessage(), 200));
        }

        AdminAuditLog::record('settings.test_mail_sent', $request->user());

        return $back->with('success', 'Email uji dikirim ke '.$data['test_email'].'. Periksa kotak masuk (dan folder spam).');
    }
}
