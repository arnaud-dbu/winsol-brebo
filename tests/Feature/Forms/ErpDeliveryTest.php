<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Entry;
use Symfony\Component\Mailer\SentMessage;
use Tests\Concerns\PassesFormTimer;
use Tests\TestCase;

/**
 * Een offerte of herstelling gaat ondertekend naar de ERP (WIN-1485, contract
 * in WIN-1479). De melding aan offertes@ is alleen nog de terugval; de
 * bevestiging aan de klant staat in ConfirmationMailTest.
 */
class ErpDeliveryTest extends TestCase
{
    use PassesFormTimer;

    private const URL = 'http://erp.ddev.site/webhooks/form/01JABCDEFGHJKMNPQRSTVWXYZ0';

    private const SECRET = 'geheim-van-het-huis';

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
        config(['services.erp.form_url' => self::URL, 'services.erp.form_secret' => self::SECRET]);
        Storage::fake('r2_private');
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
    private function quote(array $overrides = []): array
    {
        return [
            'products' => [$this->rangeSlug()],
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

    private function notifications(): int
    {
        return app('mailer')->getSymfonyTransport()->messages()
            ->filter(fn (SentMessage $message): bool => $message->getOriginalMessage()->getTo()[0]->getAddress() === 'offertes@winsolspl.be')
            ->count();
    }

    public function test_a_quote_arrives_signed_at_the_erp_and_sends_no_notification(): void
    {
        $this->freezeTime();
        Http::fake([self::URL => Http::response('OK', 200)]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote())->assertSessionHasNoErrors();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $body = json_decode($request->body(), true);

            return $request->url() === self::URL
                && $request->method() === 'POST'
                && $request->header('Content-Type')[0] === 'application/json'
                && $request->header('X-Signature-256')[0] === 'sha256='.hash_hmac('sha256', $request->body(), self::SECRET)
                && $body['form'] === 'offerte'
                && $body['language'] === 'nl'
                && is_string($body['id']) && $body['id'] !== ''
                && $body['submitted_at'] === now()->toIso8601String()
                && $body['fields'] === [
                    'products' => [$this->rangeSlug()],
                    'product' => null,
                    'location' => $this->locationSlug(),
                    'name' => 'An Peeters',
                    'phone' => '+32 470 12 34 56',
                    'email' => 'klant@voorbeeld.be',
                    'address' => 'Kerkstraat 12, 1700 Dilbeek',
                    'project' => 'Een nieuwe voordeur.',
                ]
                && $body['attachments'] === [];
        });
        $this->assertSame(0, $this->notifications(), 'Er vertrok toch een melding na een geslaagde post.');
    }

    /**
     * Een bijlage gaat als tijdelijke link op de private R2-disk, nooit als
     * route van de site: de ERP haalt ze zelf op, ook vanuit ddev.
     */
    public function test_a_repair_carries_its_photos_and_invoice_as_temporary_r2_links(): void
    {
        $this->freezeTime();
        Http::fake([self::URL => Http::response('OK', 200)]);
        Storage::disk('r2_private')->buildTemporaryUrlsUsing(
            fn (string $path, \DateTimeInterface $expiration): string => "https://r2.example/{$path}?verloopt={$expiration->getTimestamp()}",
        );

        $this->withHeader('Content-Type', 'multipart/form-data')->from('/fr/service')->post('/!/forms/herstelling', [
            'product' => $this->rangeSlug(),
            'is_winsol' => 'ja',
            'installed' => '2015',
            'facade' => 'voorgevel',
            'floor' => '0',
            'dimensions' => '240 x 180 cm',
            'problem' => 'Le volet ne descend plus.',
            'branch' => $this->locationSlug(),
            'photo' => [UploadedFile::fake()->image('volet.jpg', 800, 600)],
            'invoice' => [UploadedFile::fake()->createWithContent('facture.pdf', '%PDF-1.4 factuur')],
            'email' => 'client@exemple.be',
            'name' => 'Hélène Lefèvre',
            'phone' => '+32 470 12 34 56',
            'address' => 'Rue de l’Église 14, 1420 Braine-l’Alleud',
            'warranty' => 'nee',
            'vat_six' => '1',
            'rates_agreed' => '1',
            'warranty_terms' => '1',
            'gdpr' => '1',
            ...$this->formTimer(),
        ])->assertSessionHasNoErrors();

        $expires = now()->addHour()->getTimestamp();

        Http::assertSent(function (Request $request) use ($expires): bool {
            $body = json_decode($request->body(), true);
            [$photo, $invoice] = $body['attachments'];

            return $body['form'] === 'herstelling'
                && $body['language'] === 'fr'
                && $body['fields']['name'] === 'Hélène Lefèvre'
                && $body['fields']['vat_six'] === true
                && array_intersect(['gdpr', 'rates_agreed', 'warranty_terms', 'language', 'photo', 'invoice'], array_keys($body['fields'])) === []
                && count($body['attachments']) === 2
                && $photo['field'] === 'photo'
                && $photo['filename'] === 'volet.jpg'
                && $photo['mime'] === 'image/jpeg'
                && $photo['size'] > 0
                && $photo['url'] === "https://r2.example/herstellingen/volet.jpg?verloopt={$expires}"
                && $invoice['field'] === 'invoice'
                && $invoice['filename'] === 'facture.pdf'
                && $invoice['mime'] === 'application/pdf'
                && $invoice['size'] === 16
                && $invoice['url'] === "https://r2.example/herstellingen/facture.pdf?verloopt={$expires}";
        });
        $this->assertSame(0, $this->notifications());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function refusals(): array
    {
        return [
            'handtekening of huis fout' => [401],
            'lichaam volgt het contract niet' => [422],
            'te veel inzendingen' => [429],
            'ERP in panne' => [500],
            'ERP in onderhoud' => [503],
        ];
    }

    #[DataProvider('refusals')]
    public function test_a_refused_quote_falls_back_to_todays_mail(int $status): void
    {
        Http::fake([self::URL => Http::response('Nee', $status)]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote())->assertSessionHasNoErrors();

        Http::assertSentCount(1);
        $this->assertSame(1, $this->notifications(), "Geen melding na een {$status} van de ERP.");
    }

    public function test_an_unreachable_erp_falls_back_to_todays_mail(): void
    {
        Http::fake([self::URL => Http::failedConnection()]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote())->assertSessionHasNoErrors();

        $this->assertSame(1, $this->notifications(), 'Geen melding toen de ERP niet antwoordde.');
    }

    public function test_a_quote_carries_its_attachment(): void
    {
        Http::fake([self::URL => Http::response('OK', 200)]);
        Storage::disk('r2_private')->buildTemporaryUrlsUsing(fn (string $path): string => "https://r2.example/{$path}");

        $this->withHeader('Content-Type', 'multipart/form-data')->from('/offerte')
            ->post('/!/forms/offerte', $this->quote(['attachment' => [UploadedFile::fake()->image('voordeur.png', 400, 300)]]))
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $request): bool => json_decode($request->body(), true)['attachments'] === [[
            'field' => 'attachment',
            'filename' => 'voordeur.png',
            'mime' => 'image/png',
            'size' => Storage::disk('r2_private')->size('offertes/voordeur.png'),
            'url' => 'https://r2.example/offertes/voordeur.png',
        ]]);
    }

    /**
     * Een link die niet aangemaakt raakt, mag de aanvraag niet laten
     * verdwijnen: dan gaat de mail, met de bijlage erin.
     */
    public function test_a_failing_attachment_link_falls_back_to_todays_mail(): void
    {
        Http::fake();
        Storage::disk('r2_private')->buildTemporaryUrlsUsing(fn (): string => throw new \RuntimeException('R2 onbereikbaar'));

        $this->withHeader('Content-Type', 'multipart/form-data')->from('/offerte')
            ->post('/!/forms/offerte', $this->quote(['attachment' => [UploadedFile::fake()->image('voordeur.png', 400, 300)]]))
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertSame(1, $this->notifications());
    }

    public function test_a_brochure_request_keeps_mailing_and_skips_the_erp(): void
    {
        Http::fake();

        $this->from('/brochures')->post('/!/forms/brochure', [
            'name' => 'Klant',
            'phone' => '+32 470 00 00 00',
            'email' => 'klant@voorbeeld.be',
            'address' => 'Teststraat 1, 1700 Dilbeek',
            'gdpr' => '1',
            'brochures' => ['brochures/winsol_brochure_verticale-zonwering_nl.pdf'],
            ...$this->formTimer(),
        ])->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertGreaterThan(0, $this->notifications());
    }

    public function test_what_the_spam_filter_refuses_never_reaches_the_erp(): void
    {
        Http::fake();

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote(['address' => 'https://casino-vulkan.example/bonus']))->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertSame(0, $this->notifications());
    }

    public function test_without_an_address_or_secret_the_quote_goes_as_mail(): void
    {
        Http::fake();
        config(['services.erp.form_secret' => null]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote())->assertSessionHasNoErrors();

        Http::assertNothingSent();
        $this->assertSame(1, $this->notifications());
    }
}
