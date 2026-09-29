<?php

namespace Tests\Feature\Forms;

use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Op live komt een pagina uit de statische cache. Een formulier dat niet in
 * {{ nocache }} staat, komt na het versturen terug zonder foutmeldingen, zonder
 * ingevulde tekst en zonder bevestiging.
 *
 * `Event::forget` na het opwarmen: Statamic schrijft de pagina in de cache via
 * een luisteraar die blijft staan zolang de app leeft. Op live is dat één
 * verzoek; in een test ook de POST erna, die de gecachte pagina anders
 * overschrijft met zijn eigen redirect.
 */
class FormErrorsUnderStaticCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.static_cache' => ['driver' => 'array']]);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function strategies(): array
    {
        return [
            'zonder statische cache' => [null],
            'met statische cache, zoals op live' => ['half'],
        ];
    }

    #[DataProvider('strategies')]
    public function test_a_rejected_quote_request_comes_back_with_its_errors_and_input(?string $strategy): void
    {
        config(['statamic.static_caching.strategy' => $strategy]);

        $this->get('/offerte')->assertOk();
        Event::forget(ResponsePrepared::class);

        $response = $this->from('/offerte')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', [
                'name' => 'Poting',
                'project' => 'Photo vue de l’intérieur',
            ]);

        $response->assertRedirect('/offerte');

        $html = $this->get('/offerte')->assertOk()->getContent();

        $this->assertStringContainsString('<p class="form-error" id="', $html, 'De foutmeldingen verdwijnen achter de statische cache.');
        $this->assertStringContainsString('Photo vue de l’intérieur', $html, 'De ingevulde tekst verdwijnt achter de statische cache.');
    }

    #[DataProvider('strategies')]
    public function test_an_accepted_quote_request_shows_the_confirmation(?string $strategy): void
    {
        config(['statamic.static_caching.strategy' => $strategy, 'mail.default' => 'array']);

        $this->get('/offerte')->assertOk();
        Event::forget(ResponsePrepared::class);

        $range = Entry::query()->where('collection', 'ranges')->where('site', 'nl')->whereStatus('published')->first()->slug();
        $location = Entry::query()->where('collection', 'locations')->first()->slug();

        $this->from('/offerte')
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', [
                'products' => [$range],
                'location' => $location,
                'name' => 'Poting',
                'phone' => '+32 470 00 00 00',
                'email' => 'klant@voorbeeld.be',
                'address' => 'Teststraat 1, 1700 Dilbeek',
                'project' => 'Een nieuwe voordeur',
                'gdpr' => '1',
            ])
            ->assertSessionHasNoErrors();

        $html = $this->get('/offerte')->assertOk()->getContent();

        $this->assertStringContainsString('offerte-success', $html, 'De bevestiging verdwijnt achter de statische cache.');
    }
}
