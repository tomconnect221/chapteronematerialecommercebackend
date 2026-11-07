<?php

namespace Tests\Feature;

use App\Support\FrontendUrl;
use RuntimeException;
use Tests\TestCase;

class PasswordResetPageTest extends TestCase
{
    public function test_password_reset_links_redirect_to_the_frontend_reset_form(): void
    {
        config(['app.frontend_url' => 'https://store.example']);

        foreach (['/password-reset', '/public/password-reset'] as $path) {
            $this->get($path . '?token=test-token&email=customer%40example.com&role=customer')
                ->assertRedirect(
                    'https://store.example/password/reset?token=test-token&email=customer%40example.com&role=customer'
                );
        }
    }

    public function test_legacy_reset_link_uses_the_configured_app_origin_as_fallback(): void
    {
        config([
            'app.frontend_url' => null,
            'app.url' => 'https://backend.example/public',
        ]);

        $this->get('/public/password-reset?token=test-token&email=customer%40example.com&role=customer')
            ->assertRedirect(
                'https://backend.example/password/reset?token=test-token&email=customer%40example.com&role=customer'
            );
    }

    public function test_production_reset_link_rejects_a_local_frontend_url(): void
    {
        config([
            'app.env' => 'production',
            'app.frontend_url' => 'http://localhost:3000',
        ]);

        $this->expectException(RuntimeException::class);
        FrontendUrl::base();
    }

    public function test_production_reset_link_requires_an_explicit_frontend_url(): void
    {
        config([
            'app.env' => 'production',
            'app.frontend_url' => null,
        ]);

        $this->expectException(RuntimeException::class);
        FrontendUrl::base();
    }

    public function test_production_reset_link_accepts_a_public_https_frontend_url(): void
    {
        config([
            'app.env' => 'production',
            'app.frontend_url' => 'https://store.example/',
        ]);

        $this->assertSame('https://store.example', FrontendUrl::base());
    }
}
