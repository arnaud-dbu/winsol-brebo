<?php

namespace Tests\Feature\Content;

use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Event;
use Statamic\Facades\Entry;
use Statamic\Events\SubmissionCreated;
use Tests\TestCase;

/**
 * Een offerteknop op een productpagina linkt naar `/offerte?product=<id>`.
 * Het formulier vinkt de productgroep van dat product aan en geeft het product
 * zelf mee als verborgen veld, zodat Brebo ziet waarover de aanvraag gaat.
 */
class QuotePreselectTest extends TestCase
{
    private const SOLAR_NL = '9c5f52b1-0004-4c2d-8e6f-4a5b6c7da004';

    private const SOLAR_FR = 'c6cad995-ba48-49dc-9f16-6d954597edb7';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
    }

    /**
     * @return list<string>
     */
    private function checkedValues(string $html): array
    {
        preg_match_all('#value="([^"]*)"\s+checked#', $html, $matches);

        return $matches[1];
    }

    private function hiddenProduct(string $html): ?string
    {
        return preg_match('#<input type="hidden" name="product" value="([^"]*)"#', $html, $match) ? $match[1] : null;
    }

    public function test_the_quote_buttons_on_a_product_page_carry_the_product(): void
    {
        $html = $this->get('/aanbod/rolluiken/rolluiken-op-zonne-energie')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="/offerte"', $html, 'Er staat nog een kale offerteknop op de productpagina.');
        $this->assertGreaterThanOrEqual(
            4,
            substr_count($html, 'href="/offerte?product='.self::SOLAR_NL.'"'),
            'De quicklinkkaart, de navigatie, het mobiele menu en de vaste knop horen het product mee te geven.',
        );
    }

    public function test_a_french_product_page_links_to_the_french_form_with_its_own_slug(): void
    {
        $html = $this->get('/fr/gamme/volets-roulants/volets-solaires')->assertOk()->getContent();

        $this->assertStringContainsString('href="/fr/devis?product='.self::SOLAR_FR.'"', $html);
    }

    public function test_outside_a_product_page_the_quote_button_stays_bare(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="/offerte"', $html);
        $this->assertStringNotContainsString('/offerte?product=', $html);
    }

    public function test_the_form_checks_the_range_of_the_product_and_carries_the_product(): void
    {
        $html = $this->get('/offerte?product='.self::SOLAR_NL)->assertOk()->getContent();

        $this->assertSame(['rolluiken'], $this->checkedValues($html));
        $this->assertSame(self::SOLAR_NL, $this->hiddenProduct($html));
    }

    public function test_the_french_form_checks_the_french_range(): void
    {
        $html = $this->get('/fr/devis?product='.self::SOLAR_FR)->assertOk()->getContent();

        $this->assertSame(['volets-roulants'], $this->checkedValues($html));
        $this->assertSame(self::SOLAR_FR, $this->hiddenProduct($html));
    }

    /**
     * De taalknop neemt `product` mee, dus de Franse pagina krijgt dan het
     * Nederlandse id. Dat moet ze omzetten naar het Franse product.
     */
    public function test_a_dutch_id_on_the_french_form_turns_into_the_french_product(): void
    {
        $html = $this->get('/fr/devis?product='.self::SOLAR_NL)->assertOk()->getContent();

        $this->assertSame(['volets-roulants'], $this->checkedValues($html));
        $this->assertSame(self::SOLAR_FR, $this->hiddenProduct($html));
    }

    public function test_the_language_switch_carries_the_product(): void
    {
        $html = $this->get('/offerte?product='.self::SOLAR_NL)->assertOk()->getContent();

        $this->assertStringContainsString('/fr/devis?product='.self::SOLAR_NL.'"', $html);
    }

    /**
     * Een onbekend id mag niets aanvinken en geen verborgen waarde zetten:
     * die zou de validatie doen falen op een veld dat de klant niet ziet.
     */
    public function test_an_unknown_product_selects_nothing(): void
    {
        foreach (['/offerte?product=onzin', '/offerte'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame([], $this->checkedValues($html), $url);
            $this->assertSame('', $this->hiddenProduct($html), $url);
        }
    }

    /**
     * Ook de producten die onder twee ranges dezelfde slug dragen, zoals
     * `/aanbod/zonwering/solarfix` en `/aanbod/ramen-en-deuren/solarfix`.
     */
    public function test_every_product_preselects_its_own_range(): void
    {
        foreach (['nl' => '/offerte', 'fr' => '/fr/devis', 'en' => '/en/quote'] as $site => $form) {
            $products = Entry::query()->where('collection', 'products')->where('site', $site)->whereStatus('published')->get();

            $this->assertNotEmpty($products, "Geen producten in {$site}.");

            foreach ($products as $product) {
                $range = $product->augmentedValue('range')->value();
                $range = $range?->in($site);

                if (! $range || $range->status() !== 'published') {
                    continue;
                }

                $html = $this->get($form.'?product='.$product->id())->assertOk()->getContent();

                $this->assertSame([$range->slug()], $this->checkedValues($html), "{$site}: {$product->url()}");
                $this->assertSame($product->id(), $this->hiddenProduct($html), "{$site}: {$product->url()}");
            }
        }
    }

    public function test_after_a_validation_error_the_choice_of_the_customer_wins(): void
    {
        $this->get('/offerte?product='.self::SOLAR_NL)->assertOk();

        $this->from('/offerte?product='.self::SOLAR_NL)
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', [
                'products' => ['zonwering'],
                'product' => self::SOLAR_NL,
                'name' => 'Poting',
            ])
            ->assertRedirect('/offerte?product='.self::SOLAR_NL);

        $html = $this->get('/offerte?product='.self::SOLAR_NL)->assertOk()->getContent();

        $this->assertSame(['zonwering'], $this->checkedValues($html));
        $this->assertSame(self::SOLAR_NL, $this->hiddenProduct($html));
    }

    public function test_the_product_lands_in_the_submission_and_the_notification(): void
    {
        config(['mail.default' => 'array']);

        $submission = null;
        Event::listen(SubmissionCreated::class, function (SubmissionCreated $event) use (&$submission) {
            $submission = $event->submission;
        });

        $this->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', $this->validSubmission(['product' => self::SOLAR_FR, 'products' => ['volets-roulants']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::SOLAR_FR, $submission?->get('product'));

        $mail = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertStringContainsString('Rolluiken op zonne-energie', $mail->getHtmlBody(), 'Brebo leest het product in het Nederlands.');
        $this->assertStringContainsString('Rolluiken op zonne-energie', $mail->getTextBody());
    }

    public function test_a_quote_without_a_product_still_goes_through(): void
    {
        config(['mail.default' => 'array']);

        $this->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', $this->validSubmission(['product' => '']))
            ->assertSessionHasNoErrors();
    }

    /**
     * Het verborgen veld is van de klant aan te passen. Alleen een echt
     * product mag in de mail en de submission belanden.
     */
    public function test_a_tampered_product_is_refused(): void
    {
        $this->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/offerte', $this->validSubmission(['product' => '<a href="https://casino.example">win</a>']))
            ->assertSessionHasErrors('product', null, 'form.offerte');
    }

    /**
     * Op live bewaart de statische cache elke querystring als aparte pagina.
     * De voorselectie mag niet doorsijpelen naar de kale offertepagina, en de
     * parameter moet onder de cache nog aankomen.
     */
    public function test_the_static_cache_keeps_the_preselection_to_its_own_url(): void
    {
        config(['cache.stores.static_cache' => ['driver' => 'array']]);
        config(['statamic.static_caching.strategy' => 'half']);

        $this->get('/offerte?product='.self::SOLAR_NL)->assertOk();
        Event::forget(ResponsePrepared::class);

        $this->assertSame([], $this->checkedValues($this->get('/offerte')->assertOk()->getContent()));
        $this->assertSame(['rolluiken'], $this->checkedValues($this->get('/offerte?product='.self::SOLAR_NL)->assertOk()->getContent()));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSubmission(array $overrides = []): array
    {
        return array_merge([
            'products' => ['rolluiken'],
            'location' => Entry::query()->where('collection', 'locations')->first()->slug(),
            'name' => 'Poting',
            'phone' => '+32 470 00 00 00',
            'email' => 'klant@voorbeeld.be',
            'address' => 'Teststraat 1, 1700 Dilbeek',
            'project' => 'Rolluiken voor de achtergevel',
            'gdpr' => '1',
        ], $overrides);
    }
}
