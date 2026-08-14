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

    /**
     * `getQueryString()` sorteert parameters alfabetisch en voegt `=` toe aan
     * een waardeloze sleutel. Een campagne-URL met `?b=2&a=1` of `?0` zou zo
     * op een andere querystring landen dan de gedeelde link.
     */
    public function test_the_query_string_keeps_its_original_order_and_shape(): void
    {
        $this->request('/aanbod/rolluiken/?b=2&a=1')
            ->assertStatus(301)
            ->assertRedirect('http://localhost/aanbod/rolluiken?b=2&a=1');
    }

    /**
     * `Request::create('//evil.example.com/')` volstaat hier niet: Symfony
     * leest dat als een schemaloze absolute URL en `getPathInfo()` geeft dan
     * gewoon `/` terug. Pas een volledige URL met een dubbele slash ná de
     * host — `http://winsol-brebo.test//evil.example.com/` — laat
     * `getPathInfo()` echt `//evil.example.com/` teruggeven, precies zoals
     * het live geconstateerde verzoek dat deed.
     */
    public function test_a_double_slash_path_does_not_redirect_off_host(): void
    {
        $response = TestResponse::fromBaseResponse(
            $this->app->make(Kernel::class)->handle(
                Request::create('http://winsol-brebo.test//evil.example.com/')
            )
        );

        $response->assertStatus(301);

        $location = $response->headers->get('Location');

        $this->assertNotNull($location);
        $this->assertStringStartsWith(
            'http://winsol-brebo.test/',
            $location,
            "De omleiding wees naar een ander host: {$location}"
        );
        $this->assertStringNotContainsString(
            '//evil.example.com',
            $location,
            "De omleiding is nog steeds protocol-relatief: {$location}"
        );
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
