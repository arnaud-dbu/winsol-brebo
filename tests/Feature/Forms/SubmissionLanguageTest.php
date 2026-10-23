<?php

namespace Tests\Feature\Forms;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Events\SubmissionCreated;
use Statamic\Facades\Entry;
use Statamic\Forms\Submission;
use Tests\Concerns\PassesFormTimer;
use Tests\TestCase;

/**
 * De klant vroeg in beide audits dat de taal van de bezoeker met de
 * aanvraag meegaat. De taal komt uit de pagina waarop het formulier stond, net zoals
 * Statamic er de taal van de mails mee kiest.
 */
class SubmissionLanguageTest extends TestCase
{
    use PassesFormTimer;

    private ?Submission $stored = null;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
        Storage::fake('r2_private');

        Event::listen(SubmissionCreated::class, fn (SubmissionCreated $event) => $this->stored = $event->submission);
    }

    private function rangeSlug(string $site): string
    {
        return Entry::query()->where('collection', 'ranges')->where('site', $site)->whereStatus('published')->first()->slug();
    }

    /**
     * @return array<string, mixed>
     */
    private function quotePayload(string $site): array
    {
        return [
            'products' => [$this->rangeSlug($site)],
            'location' => Entry::query()->where('collection', 'locations')->first()->slug(),
            'name' => 'Poting',
            'phone' => '+32 470 00 00 00',
            'email' => 'klant@voorbeeld.be',
            'street' => 'Boomkwekerijstraat',
            'number' => '1',
            'postal_code' => '1600',
            'city' => 'Sint-Pieters-Leeuw',
            'project' => 'Une nouvelle porte d’entrée',
            'gdpr' => '1',
            ...$this->formTimer(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $form, string $site): array
    {
        $lead = [
            'name' => 'Klant',
            'phone' => '+32 470 00 00 00',
            'email' => 'klant@voorbeeld.be',
            'street' => 'Teststraat',
            'number' => '1',
            'postal_code' => '1700',
            'city' => 'Dilbeek',
            'gdpr' => '1',
            ...$this->formTimer(),
        ];

        return match ($form) {
            'offerte' => $this->quotePayload($site),
            'herstelling' => [
                ...$lead,
                'product' => $this->rangeSlug($site),
                'is_winsol' => 'ja',
                'installed' => '2015',
                'facade' => 'voorgevel',
                'floor' => '0',
                'dimensions' => '2m x 1m',
                'problem' => 'Het rolluik gaat niet meer omhoog',
                'branch' => Entry::query()->where('collection', 'locations')->first()->slug(),
                'warranty' => 'nee',
                'rates_agreed' => '1',
                'warranty_terms' => '1',
            ],
            'brochure' => [...$lead, 'brochures' => ['brochures/winsol_brochure_verticale-zonwering_nl.pdf']],
            'contact' => ['name' => 'Klant', 'email' => 'klant@voorbeeld.be', 'message' => 'Een vraag', ...$this->formTimer()],
        };
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function formsByPage(): array
    {
        return [
            'offerte vanaf een Franse pagina' => ['offerte', '/fr/devis', 'fr'],
            'herstelling vanaf een Engelse pagina' => ['herstelling', '/en/service', 'en'],
            'brochure vanaf een Nederlandse pagina' => ['brochure', '/brochures', 'nl'],
            'contact vanaf een Franse pagina' => ['contact', '/fr', 'fr'],
        ];
    }

    #[DataProvider('formsByPage')]
    public function test_every_form_stores_the_language_of_its_page(string $form, string $page, string $language): void
    {
        $this->from($page)->post("/!/forms/{$form}", $this->payload($form, $language))->assertSessionHasNoErrors();

        $this->assertSame($language, $this->stored?->get('language'));
    }

    /**
     * De taal komt uit de pagina en niet uit wat de bezoeker meestuurt.
     */
    public function test_a_posted_language_is_overruled_by_the_page(): void
    {
        $this->from('/fr/devis')->post('/!/forms/offerte', [...$this->quotePayload('fr'), 'language' => 'en'])->assertSessionHasNoErrors();

        $this->assertSame('fr', $this->stored?->get('language'));
    }

    public function test_the_control_panel_names_the_language(): void
    {
        $this->from('/fr')->post('/!/forms/offerte', $this->quotePayload('fr'))->assertSessionHasNoErrors();

        $this->assertSame('Frans', $this->stored?->augmentedValue('language')->value()?->label());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formPages(): array
    {
        return [
            'offerte' => ['/offerte'],
            'herstelling' => ['/service'],
            'brochure' => ['/brochures'],
        ];
    }

    #[DataProvider('formPages')]
    public function test_the_visitor_never_sees_a_language_field(string $page): void
    {
        $html = $this->get($page)->assertOk()->getContent();

        $this->assertStringContainsString('name="email"', $html, "Geen formulier gevonden op {$page}.");
        $this->assertStringNotContainsString('name="language"', $html);
    }

    /**
     * Bovenaan staat al "Ingediend in het Frans"; een tweede regel in de
     * veldenlijst zou dat alleen herhalen.
     */
    public function test_the_notification_names_the_language_once(): void
    {
        $this->from('/fr/devis')->post('/!/forms/offerte', $this->quotePayload('fr'))->assertSessionHasNoErrors();

        $notification = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();

        $this->assertMatchesRegularExpression('/Ingediend in het\s*<strong[^>]*>Frans</', $notification->getHtmlBody());
        $this->assertStringContainsString('Ingediend in het Frans', $notification->getTextBody());
        $this->assertStringNotContainsString('Taal:', $notification->getTextBody());
    }

    public function test_the_brochures_reach_a_french_visitor_in_french(): void
    {
        $this->from('/fr/brochures')->post('/!/forms/brochure', $this->payload('brochure', 'fr'))->assertSessionHasNoErrors();

        $delivery = app('mailer')->getSymfonyTransport()->messages()
            ->map->getOriginalMessage()
            ->first(fn ($message) => $message->getTo()[0]->getAddress() === 'klant@voorbeeld.be');

        $this->assertStringStartsWith('Bonjour Klant,', $delivery->getTextBody());
    }
}
