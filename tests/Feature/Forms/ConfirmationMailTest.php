<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Entry;
use Symfony\Component\Mime\Email;
use Tests\Concerns\PassesFormTimer;
use Tests\TestCase;

/**
 * Een klant die een aanvraag verstuurt, krijgt een bevestiging: los van of de
 * aanvraag in de Berichtenbak van de ERP landt of als melding per mail.
 */
class ConfirmationMailTest extends TestCase
{
    use PassesFormTimer;

    private const URL = 'http://erp.ddev.site/webhooks/form/01JABCDEFGHJKMNPQRSTVWXYZ0';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
        config(['services.erp.form_url' => self::URL, 'services.erp.form_secret' => 'geheim-van-het-huis']);
        Storage::fake('r2_private');
        Storage::disk('r2_private')->buildTemporaryUrlsUsing(fn (string $path): string => "https://r2.example/{$path}");
    }

    private function rangeSlug(string $site = 'nl'): string
    {
        return Entry::query()->where('collection', 'ranges')->where('site', $site)->whereStatus('published')->first()->slug();
    }

    private function locationSlug(): string
    {
        return Entry::query()->where('collection', 'locations')->first()->slug();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quote(string $site = 'nl', array $overrides = []): array
    {
        return [
            'products' => [$this->rangeSlug($site)],
            'location' => $this->locationSlug(),
            'name' => 'An Peeters',
            'phone' => '+32 470 12 34 56',
            'email' => 'klant@voorbeeld.be',
            'address' => 'Kerkstraat 12, 1700 Dilbeek',
            'project' => 'Een nieuwe voordeur.',
            'gdpr' => '1',
            ...$this->formTimer(),
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function repair(string $site = 'nl'): array
    {
        return [
            'product' => $this->rangeSlug($site),
            'is_winsol' => 'ja',
            'installed' => '2015',
            'facade' => 'voorgevel',
            'floor' => '0',
            'dimensions' => '240 x 180 cm',
            'problem' => 'Het rolluik gaat niet meer omlaag.',
            'branch' => $this->locationSlug(),
            'email' => 'klant@voorbeeld.be',
            'name' => 'An Peeters',
            'phone' => '+32 470 12 34 56',
            'address' => 'Kerkstraat 12, 1700 Dilbeek',
            'warranty' => 'nee',
            'vat_six' => '1',
            'rates_agreed' => '1',
            'warranty_terms' => '1',
            'gdpr' => '1',
            ...$this->formTimer(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $form): array
    {
        return match ($form) {
            'offerte' => $this->quote(),
            'herstelling' => $this->repair(),
            'contact' => ['name' => 'An Peeters', 'email' => 'klant@voorbeeld.be', 'message' => 'Een vraag.', ...$this->formTimer()],
        };
    }

    /**
     * @return array<int, Email>
     */
    private function mailsTo(string $address): array
    {
        return app('mailer')->getSymfonyTransport()->messages()
            ->map->getOriginalMessage()
            ->filter(fn (Email $message): bool => $message->getTo()[0]->getAddress() === $address)
            ->values()
            ->all();
    }

    public function test_a_quote_the_erp_accepts_still_confirms_to_the_customer(): void
    {
        Http::fake([self::URL => Http::response('OK', 200)]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote())->assertSessionHasNoErrors();

        $this->assertCount(1, $this->mailsTo('klant@voorbeeld.be'), 'De klant kreeg geen bevestiging.');
        $this->assertCount(0, $this->mailsTo('offertes@winsolspl.be'), 'De melding vertrok toch, naast de Berichtenbak.');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function forms(): array
    {
        return [
            'offerte' => ['offerte', '/offerte'],
            'herstelling' => ['herstelling', '/service'],
            'contact' => ['contact', '/'],
        ];
    }

    #[DataProvider('forms')]
    public function test_every_request_form_confirms_to_the_customer(string $form, string $page): void
    {
        Http::fake([self::URL => Http::response('OK', 200)]);

        $this->from($page)->post("/!/forms/{$form}", $this->payload($form))->assertSessionHasNoErrors();

        $this->assertCount(1, $this->mailsTo('klant@voorbeeld.be'), "Het {$form}formulier stuurde geen bevestiging.");
    }

    public function test_a_refused_quote_sends_both_the_notification_and_the_confirmation(): void
    {
        Http::fake([self::URL => Http::response('Nee', 503)]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote())->assertSessionHasNoErrors();

        $this->assertCount(1, $this->mailsTo('offertes@winsolspl.be'));
        $this->assertCount(1, $this->mailsTo('klant@voorbeeld.be'));
    }

    public function test_spam_gets_no_confirmation(): void
    {
        Http::fake();

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote(overrides: ['address' => 'https://casino-vulkan.example/bonus']))->assertSessionHasNoErrors();

        $this->assertCount(0, $this->mailsTo('klant@voorbeeld.be'));
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function languages(): array
    {
        return [
            'Nederlands' => ['/offerte', 'nl', 'We hebben je aanvraag goed ontvangen', 'Dag An Peeters,'],
            'Frans' => ['/fr/devis', 'fr', 'Nous avons bien reçu votre demande', 'Bonjour An Peeters,'],
            'Engels' => ['/en/quote', 'en', 'We have received your request', 'Hello An Peeters,'],
        ];
    }

    #[DataProvider('languages')]
    public function test_the_confirmation_speaks_the_language_of_the_site(string $page, string $site, string $subject, string $greeting): void
    {
        Http::fake([self::URL => Http::response('OK', 200)]);

        $this->from($page)->post('/!/forms/offerte', $this->quote($site))->assertSessionHasNoErrors();

        [$confirmation] = $this->mailsTo('klant@voorbeeld.be');
        $this->assertSame($subject, $confirmation->getSubject());
        $this->assertStringStartsWith($greeting, $confirmation->getTextBody());
        $this->assertStringContainsString($greeting, $confirmation->getHtmlBody());
    }

    public function test_a_reply_to_the_confirmation_reaches_winsol_brebo(): void
    {
        Http::fake([self::URL => Http::response('OK', 200)]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote())->assertSessionHasNoErrors();

        [$confirmation] = $this->mailsTo('klant@voorbeeld.be');
        $this->assertSame('offertes@winsolspl.be', $confirmation->getReplyTo()[0]->getAddress());
        $this->assertSame(config('mail.from.address'), $confirmation->getFrom()[0]->getAddress());
    }

    /**
     * Akkoordvinkjes en de taal zijn geen antwoorden van de klant; een
     * bijlage staat er alleen met haar naam in, zonder link naar R2.
     */
    public function test_the_confirmation_repeats_what_the_customer_filled_in(): void
    {
        Http::fake([self::URL => Http::response('OK', 200)]);

        $this->withHeader('Content-Type', 'multipart/form-data')->from('/fr/service')->post('/!/forms/herstelling', [
            ...$this->repair('fr'),
            'problem' => 'Le volet ne descend plus.',
            'photo' => [UploadedFile::fake()->image('volet.jpg', 800, 600)],
            'invoice' => [UploadedFile::fake()->createWithContent('facture.pdf', '%PDF-1.4 factuur')],
        ])->assertSessionHasNoErrors();

        [$confirmation] = $this->mailsTo('klant@voorbeeld.be');

        foreach ([$confirmation->getHtmlBody(), $confirmation->getTextBody()] as $body) {
            $this->assertStringContainsString('Quel est le problème ?', $body);
            $this->assertStringContainsString('Le volet ne descend plus.', $body);
            $this->assertStringContainsString('Téléphone', $body);
            $this->assertStringContainsString('Façade avant', $body);
            $this->assertStringContainsString('Application de la TVA à 6 %', $body);
            $this->assertStringContainsString('volet.jpg', $body);
            $this->assertStringContainsString('facture.pdf', $body);
            $this->assertStringNotContainsString('r2.example', $body);
            $this->assertStringNotContainsString('conditions de garantie', $body);
            $this->assertStringNotContainsString('Brebo BV', $body);
            $this->assertStringNotContainsString('traitement de mes données', $body);
            $this->assertStringNotContainsString('Akkoord', $body);
            $this->assertStringNotContainsString('Taal', $body);
        }
    }
}
