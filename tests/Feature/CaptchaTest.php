<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AppSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AppSettings;
use App\Services\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** CAPTCHA di login / lupa kata sandi / pendaftaran: provider bawaan (hitung), provider eksternal, CSP, dan menu pengaturan. */
class CaptchaTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secure-Test-Password-2026!';

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makeAdmin(array $permissions = ['dashboard.view']): Admin
    {
        $n = ++$this->seq;
        $role = Role::create(['name' => "cap-role-$n", 'label' => "Role $n", 'guard_name' => 'admin']);
        foreach ($permissions as $p) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'admin'], ['label' => $p])->id);
        }
        $admin = Admin::create(['name' => "Admin $n", 'username' => "cap$n", 'email' => "cap$n@example.test", 'password' => self::PASSWORD, 'is_active' => true]);
        $admin->syncRoles([$role->id]);

        return $admin;
    }

    private function useProvider(string $provider, bool $keys = true): void
    {
        config([
            'captcha.provider' => $provider,
            'captcha.site_key' => $keys ? 'site-key-123' : null,
            'captcha.secret_key' => $keys ? 'secret-key-456' : null,
        ]);
    }

    /** Ambil pertanyaan "math" dari halaman login dan kembalikan [token, jawaban benar]. */
    private function solveMath(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match('/name="captcha_token" value="([^"]+)"/', $html, $t);
        preg_match('/<strong class="text-nowrap">([^<]+)<\/strong>/', html_entity_decode($html), $q);
        $this->assertNotEmpty($t, 'token CAPTCHA tidak ada di halaman');

        $expr = trim($q[1]);
        $answer = match (true) {
            str_contains($expr, '+') => array_sum(array_map('intval', explode('+', $expr))),
            str_contains($expr, '−') => (int) explode('−', $expr)[0] - (int) explode('−', $expr)[1],
            default => array_product(array_map('intval', explode('×', $expr))),
        };

        return [$t[1], $answer];
    }

    // ---- nonaktif -------------------------------------------------------

    public function test_nothing_changes_when_captcha_is_off(): void
    {
        $admin = $this->makeAdmin();

        $this->get(route('admin.login'))->assertOk()->assertDontSee('captcha_token')->assertDontSee('data-sitekey');
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
    }

    // ---- math -----------------------------------------------------------

    public function test_math_captcha_blocks_login_without_or_with_a_wrong_answer(): void
    {
        $this->useProvider('math', keys: false);
        $admin = $this->makeAdmin();
        $creds = ['username' => $admin->username, 'password' => self::PASSWORD];

        $this->from(route('admin.login'))->post(route('admin.login.store'), $creds)->assertSessionHasErrors('captcha');
        $this->assertGuest('admin');

        [$token] = $this->solveMath(route('admin.login'));
        $this->from(route('admin.login'))->post(route('admin.login.store'), $creds + ['captcha_token' => $token, 'captcha_answer' => '9999'])
            ->assertSessionHasErrors('captcha');
        $this->assertGuest('admin');
    }

    public function test_math_captcha_accepts_the_right_answer_once(): void
    {
        $this->useProvider('math', keys: false);
        $admin = $this->makeAdmin();
        [$token, $answer] = $this->solveMath(route('admin.login'));
        $creds = ['username' => $admin->username, 'password' => self::PASSWORD, 'captcha_token' => $token, 'captcha_answer' => (string) $answer];

        $this->post(route('admin.login.store'), $creds)->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');

        // Pertanyaan yang sama tidak bisa dipakai lagi.
        $this->post(route('admin.logout'));
        $this->from(route('admin.login'))->post(route('admin.login.store'), $creds)->assertSessionHasErrors('captcha');
        $this->assertGuest('admin');
    }

    public function test_failed_captcha_does_not_count_towards_the_account_lockout(): void
    {
        $this->useProvider('math', keys: false);
        $admin = $this->makeAdmin();

        for ($i = 0; $i < 12; $i++) {
            $this->from(route('admin.login'))->post(route('admin.login.store'), ['username' => $admin->username, 'password' => 'salah']);
        }

        [$token, $answer] = $this->solveMath(route('admin.login'));
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD, 'captcha_token' => $token, 'captcha_answer' => (string) $answer])
            ->assertRedirect(route('admin.dashboard'));
    }

    // ---- provider eksternal --------------------------------------------

    public function test_turnstile_widget_is_rendered_and_csp_allows_it(): void
    {
        $this->useProvider('turnstile');

        $this->get(route('admin.login'))->assertOk()
            ->assertSee('cf-turnstile', false)->assertSee('data-sitekey="site-key-123"', false)
            ->assertDontSee('secret-key-456');

        $csp = $this->get(route('admin.login'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://challenges.cloudflare.com', $csp);
        $this->assertStringContainsString("frame-src 'self' https://challenges.cloudflare.com", $csp);
    }

    public function test_csp_is_unchanged_when_captcha_is_off(): void
    {
        $csp = $this->get(route('admin.login'))->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('frame-src', $csp);
        $this->assertStringNotContainsString('cloudflare', $csp);
        $this->assertStringContainsString("connect-src 'self';", $csp);
    }

    public function test_external_provider_verifies_the_token_with_the_secret(): void
    {
        $this->useProvider('turnstile');
        $admin = $this->makeAdmin();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD, 'cf-turnstile-response' => 'token-abc'])
            ->assertRedirect(route('admin.dashboard'));

        Http::assertSent(fn ($r) => $r->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $r['secret'] === 'secret-key-456' && $r['response'] === 'token-abc');
    }

    public function test_external_provider_rejects_failed_missing_and_unreachable_verification(): void
    {
        $this->useProvider('recaptcha');
        $admin = $this->makeAdmin();
        $creds = ['username' => $admin->username, 'password' => self::PASSWORD];

        Http::fake(['www.google.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
        $this->from(route('admin.login'))->post(route('admin.login.store'), $creds + ['g-recaptcha-response' => 'x'])->assertSessionHasErrors('captcha');

        // Tanpa token tidak ada permintaan ke penyedia.
        Http::fake();
        $this->from(route('admin.login'))->post(route('admin.login.store'), $creds)->assertSessionHasErrors('captcha');
        Http::assertNothingSent();

        Http::fake(['www.google.com/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->from(route('admin.login'))->post(route('admin.login.store'), $creds + ['g-recaptcha-response' => 'x'])->assertSessionHasErrors('captcha');
        $this->assertGuest('admin');
    }

    public function test_external_provider_without_keys_is_not_enforced_so_nobody_is_locked_out(): void
    {
        $this->useProvider('hcaptcha', keys: false);
        $admin = $this->makeAdmin();

        $this->assertTrue(app(Captcha::class)->isMisconfigured());
        $this->get(route('admin.login'))->assertOk()->assertDontSee('h-captcha');
        $this->post(route('admin.login.store'), ['username' => $admin->username, 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
    }

    // ---- halaman lain ---------------------------------------------------

    public function test_forgot_password_and_registration_are_protected_too(): void
    {
        $this->useProvider('math', keys: false);
        config(['domains.pelajar_registration' => true]);

        foreach ([route('admin.password.request'), route('pegawai.password.request'), route('pelajar.password.request'), route('pelajar.register')] as $url) {
            $this->get($url)->assertOk()->assertSee('captcha_token', false);
        }

        $this->from(route('admin.password.request'))->post(route('admin.password.email'), ['email' => 'a@example.test'])->assertSessionHasErrors('captcha');
        $this->from(route('pelajar.register'))->post(route('pelajar.register.store'), [
            'nis' => '123', 'birth_date' => '2010-01-01', 'password' => 'Abcdefgh1', 'password_confirmation' => 'Abcdefgh1',
        ])->assertSessionHasErrors('captcha');
    }

    public function test_pages_can_be_switched_off_individually(): void
    {
        $this->useProvider('math', keys: false);
        config(['captcha.pages.forgot' => false]);

        $this->get(route('admin.password.request'))->assertOk()->assertDontSee('captcha_token', false);
        $this->get(route('admin.login'))->assertOk()->assertSee('captcha_token', false);
    }

    public function test_the_two_factor_challenge_page_does_not_need_a_captcha(): void
    {
        $this->useProvider('math', keys: false);
        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
    }

    // ---- menu pengaturan --------------------------------------------------

    public function test_settings_menu_saves_captcha_with_an_encrypted_secret_and_applies_it(): void
    {
        $admin = $this->makeAdmin(['dashboard.view', 'settings.view', 'settings.update']);
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.settings.index', ['tab' => 'captcha']))->assertOk()->assertSee('Jenis CAPTCHA');

        // Provider eksternal tanpa site key ditolak.
        $this->put(route('admin.settings.update', 'captcha'), ['captcha_provider' => 'turnstile', 'captcha_site_key' => '', 'captcha_secret_key' => 'rahasia', 'captcha_login' => '1'])
            ->assertSessionHasErrors('captcha_site_key');

        $this->put(route('admin.settings.update', 'captcha'), [
            'captcha_provider' => 'turnstile', 'captcha_site_key' => 'pub-key', 'captcha_secret_key' => 'rahasia-sekali',
            'captcha_login' => '1', 'captcha_forgot' => '1',
        ])->assertSessionHas('success');

        $row = AppSetting::where('key', 'captcha_secret_key')->first();
        $this->assertTrue((bool) $row->is_secret);
        $this->assertStringNotContainsString('rahasia-sekali', (string) $row->value);
        $this->assertSame('rahasia-sekali', Crypt::decryptString($row->value));

        app(AppSettings::class)->apply();
        $this->assertSame('turnstile', config('captcha.provider'));
        $this->assertSame('pub-key', config('captcha.site_key'));
        $this->assertSame('rahasia-sekali', config('captcha.secret_key'));
        $this->assertTrue(config('captcha.pages.login'));
        $this->assertFalse(config('captcha.pages.register'), 'halaman yang tidak dicentang dimatikan');
        $this->assertTrue(app(Captcha::class)->enabledFor('login'));
    }

    public function test_settings_page_never_prints_the_secret_key(): void
    {
        $admin = $this->makeAdmin(['dashboard.view', 'settings.view', 'settings.update']);
        $this->useProvider('turnstile');
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.settings.index', ['tab' => 'captcha']))->assertOk()->assertDontSee('secret-key-456');
    }
}
