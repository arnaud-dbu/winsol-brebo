<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Laravel negeert een afsluitende slash bij het matchen van routes, dus
 * `/aanbod/rolluiken/` gaf net zo goed een 200 als `/aanbod/rolluiken`. Twee
 * URL's voor dezelfde pagina; de canonical ving het duplicate-contentrisico al
 * op, maar Google crawlt ze intussen wel allebei.
 */
class TrailingSlashTest extends TestCase
{
    /**
     * `$this->get()` kan hier niet: `prepareUrlForRequest()` trimt de slash er
     * onderweg af, waardoor de test nooit verstuurt wat hij wil toetsen. Via de
     * kernel loopt het verzoek wél door de echte middleware-stack.
     */
    private function request(string $uri, string $method = 'GET'): TestResponse
    {
        $response = $this->app->make(Kernel::class)->handle(
            Request::create('http://localhost'.$uri, $method)
        );

        return TestResponse::fromBaseResponse($response);
    }

    public function test_a_trailing_slash_redirects_permanently_to_the_canonical_url(): void
    {
        $this->request('/aanbod/rolluiken/')
            ->assertStatus(301)
            ->assertRedirect('http://localhost/aanbod/rolluiken');
    }

    public function test_the_query_string_survives_the_redirect(): void
    {
        $this->request('/aanbod/rolluiken/?range=screens')
            ->assertStatus(301)
            ->assertRedirect('http://localhost/aanbod/rolluiken?range=screens');
    }

    public function test_the_homepage_keeps_its_slash(): void
    {
        $this->request('/')->assertOk();
    }

    public function test_a_url_without_a_trailing_slash_is_served_as_is(): void
    {
        $this->request('/aanbod/rolluiken')->assertOk();
    }

    /**
     * Alleen GET en HEAD. Een 301 op een POST laat de browser opnieuw
     * versturen zonder body, dus een formulier zou stilzwijgend leeglopen.
     */
    public function test_a_post_is_left_alone(): void
    {
        $this->assertNotSame(
            301,
            $this->request('/aanbod/rolluiken/', 'POST')->getStatusCode(),
            'Een POST hoort de applicatie te bereiken, niet omgeleid te worden.'
        );
    }
}
