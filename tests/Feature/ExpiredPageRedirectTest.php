<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** 419 (token CSRF/sesi kedaluwarsa) kembali ke halaman asal dengan pesan, bukan halaman buntu. */
class ExpiredPageRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF dimatikan saat test, jadi kegagalannya ditiru dengan melempar pengecualian yang sama.
        Route::middleware('web')->post('/_uji-419', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
    }

    public function test_expired_form_goes_back_to_the_page_with_a_message(): void
    {
        $this->from('/halaman-asal')->post('/_uji-419', ['password' => 'rahasia'])
            ->assertRedirect('/halaman-asal')
            ->assertSessionHas('error', 'Halaman sudah kedaluwarsa. Silakan coba lagi.')
            ->assertSessionMissing('_old_input');
    }

    public function test_json_requests_still_get_419(): void
    {
        $this->postJson('/_uji-419')->assertStatus(419);
    }
}
