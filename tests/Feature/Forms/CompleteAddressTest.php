<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Entry;
use Symfony\Component\Mime\Email;
use Tests\Concerns\PassesFormTimer;
use Tests\TestCase;

/**
 * Een aanvraag vraagt een volledig adres (#21): straat, huisnummer, postcode
 * en gemeente in vier eigen vakken, in plaats van één vrij tekstveld waarin
 * de gemeente of het nummer ontbrak.
 */
class CompleteAddressTest extends TestCase
{
    use PassesFormTimer;

    private const PAGES = ['offerte' => '/offerte', 'herstelling' => '/service', 'brochure' => '/brochures'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array']);
        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
        config(['services.erp.form_url' => null, 'services.erp.form_secret' => null]);
        Storage::fake('r2_private');
    }

    private function rangeSlug(): string
    {
        return Entry::query()->where('collection', 'ranges')->where('site', 'nl')->whereStatus('published')->first()->slug();
    }

    private function locationSlug(): string
    {
        return Entry::query()->where('collection', 'locations')->first()->slug();
    }

    /**
     * @return array<string, string>
     */
    private function address(): array
    {
        return ['street' => 'Kerkstraat', 'number' => '12', 'postal_code' => '1700', 'city' => 'Dilbeek'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $form, array $overrides = []): array
    {
        $fields = match ($form) {
            'offerte' => [
                'products' => [$this->rangeSlug()],
                'location' => $this->locationSlug(),
                'name' => 'An Peeters',
                'phone' => '+32 470 12 34 56',
                'email' => 'klant@voorbeeld.be',
                'project' => 'Een nieuwe voordeur.',
                'gdpr' => '1',
            ],
            'herstelling' => [
                'product' => $this->rangeSlug(),
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
                'warranty' => 'nee',
                'rates_agreed' => '1',
                'warranty_terms' => '1',
                'gdpr' => '1',
            ],
            'brochure' => [
                'brochures' => ['brochures/winsol_brochure_verticale-zonwering_nl.pdf'],
                'name' => 'An Peeters',
                'phone' => '+32 470 12 34 56',
                'email' => 'klant@voorbeeld.be',
                'gdpr' => '1',
            ],
        };

        return [...$fields, ...$this->address(), ...$this->formTimer(), ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    private function errorsAfterSending(string $form, array $overrides = [], string $page = ''): array
    {
        $this->from($page ?: self::PAGES[$form])->post('/!/forms/'.$form, $this->payload($form, $overrides));

        $errors = session('errors')?->getBag('form.'.$form)->toArray() ?? [];

        return array_map(fn (array $messages): string => $messages[0], $errors);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forms(): array
    {
        return ['offerte' => ['offerte'], 'herstelling' => ['herstelling'], 'brochure' => ['brochure']];
    }

    #[DataProvider('forms')]
    public function test_a_request_without_a_house_number_is_refused(string $form): void
    {
        $errors = $this->errorsAfterSending($form, ['number' => '']);

        $this->assertSame(['number' => 'Vul je huisnummer in.'], $errors);
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function languages(): array
    {
        return [
            'nl' => ['/offerte', [
                'street' => 'Vul je straat in.',
                'number' => 'Vul je huisnummer in.',
                'postal_code' => 'Vul je postcode in.',
                'city' => 'Vul je gemeente in.',
                'invalid_postal_code' => 'Vul een geldige postcode in (4 cijfers).',
            ]],
            'fr' => ['/fr/devis', [
                'street' => 'Indiquez votre rue.',
                'number' => 'Indiquez votre numéro.',
                'postal_code' => 'Indiquez votre code postal.',
                'city' => 'Indiquez votre commune.',
                'invalid_postal_code' => 'Indiquez un code postal valide (4 chiffres).',
            ]],
            'en' => ['/en/quote', [
                'street' => 'Enter your street.',
                'number' => 'Enter your house number.',
                'postal_code' => 'Enter your postcode.',
                'city' => 'Enter your town.',
                'invalid_postal_code' => 'Enter a valid postcode (4 digits).',
            ]],
        ];
    }

    /**
     * @param  array<string, string>  $expected
     */
    #[DataProvider('languages')]
    public function test_every_empty_part_of_the_address_gets_its_own_message(string $page, array $expected): void
    {
        $errors = $this->errorsAfterSending('offerte', ['street' => '', 'number' => '', 'postal_code' => '', 'city' => ''], $page);

        $this->assertSame([
            'street' => $expected['street'],
            'number' => $expected['number'],
            'postal_code' => $expected['postal_code'],
            'city' => $expected['city'],
        ], $errors);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPostalCodes(): array
    {
        return [
            'drie cijfers' => ['170'],
            'vijf cijfers' => ['17000'],
            'met letters' => ['B-1700'],
            'Nederlandse vorm' => ['1012 AB'],
        ];
    }

    #[DataProvider('invalidPostalCodes')]
    public function test_a_postal_code_that_is_not_four_digits_is_refused(string $postalCode): void
    {
        $errors = $this->errorsAfterSending('offerte', ['postal_code' => $postalCode]);

        $this->assertSame(['postal_code' => 'Vul een geldige postcode in (4 cijfers).'], $errors);
    }

    /**
     * @param  array<string, string>  $expected
     */
    #[DataProvider('languages')]
    public function test_the_invalid_postal_code_message_follows_the_language(string $page, array $expected): void
    {
        $errors = $this->errorsAfterSending('offerte', ['postal_code' => '170'], $page);

        $this->assertSame(['postal_code' => $expected['invalid_postal_code']], $errors);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function houseNumbers(): array
    {
        return ['cijfers' => ['12'], 'met letter' => ['12A'], 'met bus' => ['12 bus 3']];
    }

    #[DataProvider('houseNumbers')]
    public function test_a_house_number_may_carry_a_letter_or_a_box(string $number): void
    {
        $this->assertSame([], $this->errorsAfterSending('offerte', ['number' => $number]));
    }

    public function test_the_erp_receives_the_four_parts_and_no_composed_address(): void
    {
        config(['services.erp.form_url' => 'http://erp.ddev.site/webhooks/form/01JABCDEFGHJKMNPQRSTVWXYZ0', 'services.erp.form_secret' => 'geheim']);
        Http::fake(['erp.ddev.site/*' => Http::response('OK', 200)]);

        $this->from('/offerte')->post('/!/forms/offerte', $this->payload('offerte', ['number' => '12 bus 3']))->assertSessionHasNoErrors();

        Http::assertSent(function (Request $request): bool {
            $fields = json_decode($request->body(), true)['fields'];

            return ! array_key_exists('address', $fields)
                && $fields['street'] === 'Kerkstraat'
                && $fields['number'] === '12 bus 3'
                && $fields['postal_code'] === '1700'
                && $fields['city'] === 'Dilbeek';
        });
    }

    private function mailTo(string $address, string $subject = ''): Email
    {
        $mails = app('mailer')->getSymfonyTransport()->messages()
            ->map->getOriginalMessage()
            ->filter(fn (Email $message): bool => $message->getTo()[0]->getAddress() === $address && str_contains($message->getSubject(), $subject));

        $this->assertCount(1, $mails, "Geen of meer dan één mail aan {$address}.");

        return $mails->first();
    }

    /**
     * Zonder ERP-configuratie gaan offerte en herstelling als melding; de
     * brochure gaat altijd zo.
     */
    #[DataProvider('forms')]
    public function test_the_notification_shows_the_address_as_one_line_with_a_maps_link(string $form): void
    {
        $this->from(self::PAGES[$form])->post('/!/forms/'.$form, $this->payload($form))->assertSessionHasNoErrors();

        $notification = $this->mailTo('offertes@winsolspl.be');
        $html = $notification->getHtmlBody();

        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&query=Kerkstraat+12%2C+1700+Dilbeek"', $html);
        $this->assertMatchesRegularExpression('/>\s*Adres\s*</', $html);
        $this->assertMatchesRegularExpression('/>\s*Kerkstraat 12, 1700 Dilbeek\s*</', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*(Straat|Nr\.|Postcode|Gemeente)\s*</', $html);

        $this->assertStringContainsString("Adres: Kerkstraat 12, 1700 Dilbeek\n", $notification->getTextBody());
        $this->assertDoesNotMatchRegularExpression('/^(Straat|Nr\.|Postcode|Gemeente):/m', $notification->getTextBody());
    }

    public function test_the_confirmation_shows_the_address_as_one_line(): void
    {
        $this->from('/fr/devis')->post('/!/forms/offerte', $this->payload('offerte', ['number' => '12 bus 3']))->assertSessionHasNoErrors();

        $confirmation = $this->mailTo('klant@voorbeeld.be');

        $this->assertMatchesRegularExpression('/>\s*Adresse\s*</', $confirmation->getHtmlBody());
        $this->assertMatchesRegularExpression('/>\s*Kerkstraat 12 bus 3, 1700 Dilbeek\s*</', $confirmation->getHtmlBody());
        $this->assertDoesNotMatchRegularExpression('/>\s*(Rue|N°|Code postal|Commune)\s*</', $confirmation->getHtmlBody());

        $this->assertStringContainsString("Adresse: Kerkstraat 12 bus 3, 1700 Dilbeek\n", $confirmation->getTextBody());
    }
}
