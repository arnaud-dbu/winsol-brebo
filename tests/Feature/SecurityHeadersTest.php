<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_every_response_limits_what_leaks_to_other_domains(): void
    {
        $this->get('/nieuws')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * `RedirectTrailingSlash` retourneert een 301 zonder `$next` aan te
     * roepen. Stond hij vóór de headermiddleware, dan liep die nooit — de
     * omleiding zelf droeg dan geen enkele beveiligingsheader.
     */
    public function test_the_trailing_slash_redirect_still_carries_the_headers(): void
    {
        $response = TestResponse::fromBaseResponse(
            $this->app->make(Kernel::class)->handle(
                Request::create('http://winsol-brebo.test/nieuws/')
            )
        );

        $response->assertStatus(301)
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_every_response_closes_the_device_apis_the_site_never_uses(): void
    {
        $this->get('/nieuws')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    /**
     * De headers horen ook op de API en het control panel te zitten, dus ze
     * hangen net als de noindex-header globaal en niet aan de `web`-groep.
     */
    public function test_the_api_carries_them_too(): void
    {
        $this->getJson('/api/inspace/v1/schema')
            ->assertStatus(401)
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_a_secure_request_asks_the_browser_to_stay_on_https(): void
    {
        $this->get('https://winsol-brebo.test/nieuws')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /**
     * HSTS over een onbeveiligde verbinding is zinloos: RFC 6797 schrijft voor
     * dat de browser hem daar negeert én dat de server hem daar niet stuurt.
     * Lokaal draait de site op http, dus zonder deze grens zou elke
     * ontwikkelaar de header binnenkrijgen zonder dat hij iets doet.
     */
    public function test_an_insecure_request_gets_no_hsts(): void
    {
        $this->assertNull(
            $this->get('http://winsol-brebo.test/nieuws')->headers->get('Strict-Transport-Security'),
            'HSTS hoort niet over http te gaan.'
        );
    }
}
