<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NoIndexHeaderTest extends TestCase
{
    public function test_a_non_indexable_site_marks_every_response_as_noindex(): void
    {
        config()->set('app.indexable', false);

        $this->get('/nieuws')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * `RedirectTrailingSlash` retourneert een 301 zonder `$next` aan te
     * roepen. Stond hij vóór `NoIndexHeader`, dan liep die nooit en kroop een
     * afgeschermde omgeving alsnog via de omleiding de index in.
     */
    public function test_the_trailing_slash_redirect_stays_noindex_too(): void
    {
        config()->set('app.indexable', false);

        $response = TestResponse::fromBaseResponse(
            $this->app->make(Kernel::class)->handle(
                Request::create('http://winsol-brebo.test/nieuws/')
            )
        );

        $response->assertStatus(301)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * De header moet ook op de API zitten. Die is het enige deel dat een
     * externe partij aanroept, en een gelekte JSON-respons in de index is
     * even ongewenst als een pagina.
     */
    public function test_the_api_carries_the_header_too(): void
    {
        config()->set('app.indexable', false);

        $this->getJson('/api/inspace/v1/schema')
            ->assertStatus(401)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_an_indexable_site_carries_no_such_header(): void
    {
        config()->set('app.indexable', true);

        $this->assertNull(
            $this->get('/nieuws')->headers->get('X-Robots-Tag'),
            'Een gewone site mag zichzelf niet uit de zoekresultaten houden.'
        );
    }
}
