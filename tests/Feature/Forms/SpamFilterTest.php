<?php

namespace Tests\Feature\Forms;

use App\Services\FormTimer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Events\SubmissionCreated;
use Statamic\Facades\Entry;
use Statamic\Forms\Submission;
use Tests\Concerns\PassesFormTimer;
use Tests\TestCase;

/**
 * Een geweigerde inzending verdwijnt stil: de bezoeker ziet het bedankscherm,
 * Winsol krijgt niets. Deze tests leggen vast wat een bot tegenhoudt en dat
 * een echte klant er in elk van de drie talen door komt.
 */
class SpamFilterTest extends TestCase
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
    private function repair(array $overrides = []): array
    {
        return [
            'product' => $this->rangeSlug(),
            'is_winsol' => 'ja',
            'installed' => '2015',
            'facade' => 'voorgevel',
            'floor' => '0',
            'dimensions' => '240 x 180 cm',
            'problem' => 'Het rolluik gaat niet meer omhoog.',
            'branch' => $this->locationSlug(),
            'email' => 'klant@voorbeeld.be',
            'name' => 'An Peeters',
            'phone' => '0470 12 34 56',
            'address' => 'Kerkstraat 12, 1700 Dilbeek',
            'warranty' => 'nee',
            'rates_agreed' => '1',
            'warranty_terms' => '1',
            'gdpr' => '1',
            ...$this->formTimer(),
            ...$overrides,
        ];
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function errorsAfterPosting(string $form, string $page, array $payload): array
    {
        $this->from($page)->post("/!/forms/{$form}", $payload);

        $errors = session('errors')?->getBag("form.{$form}")->toArray() ?? [];

        return array_map(fn (array $messages): string => $messages[0], $errors);
    }

    private function assertRejectedAsSpam(string $form, string $reason): void
    {
        $this->assertNull($this->stored, 'De inzending werd toch opgeslagen.');

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Inzending geweigerd als spam.'
                && $context['formulier'] === $form
                && $context['reden'] === $reason)
            ->once();
    }

    /**
     * Het voorbeeld uit het ticket, zoals het binnenkwam. De getallen in het
     * jaar- en verdiepingsveld en het telefoonnummer vallen al bij de
     * validatie af, en die weigering logt LogRejectedFormSubmissions.
     */
    public function test_the_spam_from_the_ticket_fails_validation_and_is_logged(): void
    {
        Log::spy();

        $errors = $this->errorsAfterPosting('herstelling', '/service', $this->repair([
            'installed' => '478951',
            'floor' => '572661',
            'problem' => 'Лучшее онлайн-казино, бонус при регистрации',
            'address' => 'https://casino-vulkan.example/bonus',
            'phone' => '89123456789',
        ]));

        $this->assertArrayHasKey('installed', $errors);
        $this->assertArrayHasKey('floor', $errors);
        $this->assertArrayHasKey('phone', $errors);
        $this->assertNull($this->stored);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Inzending geweigerd bij de validatie.'
                && $context['formulier'] === 'herstelling')
            ->once();
    }

    /**
     * Dezelfde bot met getallen die wel kloppen. Dan vangt het inhoudsfilter
     * hem, en dat gebeurt stil: de bot krijgt geen foutmelding om op bij te
     * sturen.
     */
    public function test_cyrillic_text_is_rejected_silently(): void
    {
        Log::spy();

        $this->from('/service')
            ->post('/!/forms/herstelling', $this->repair(['problem' => 'Лучшее онлайн-казино, бонус при регистрации']))
            ->assertSessionHasNoErrors();

        $this->assertRejectedAsSpam('herstelling', 'Cyrillisch schrift in problem');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function links(): array
    {
        return [
            'adres met https' => ['address', 'https://casino-vulkan.example/bonus'],
            'adres met www' => ['address', 'www.casino-vulkan.ru'],
            'naam met een kale domeinnaam' => ['name', 'Bonus op vulkan-casino.com'],
            'naam met een pad' => ['name', 'Jan bit.ly/abc123'],
        ];
    }

    #[DataProvider('links')]
    public function test_a_link_in_name_or_address_is_rejected(string $field, string $value): void
    {
        Log::spy();

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote([$field => $value]))->assertSessionHasNoErrors();

        $this->assertRejectedAsSpam('offerte', "link in {$field}");
    }

    /**
     * Een klant mag in de beschrijving van zijn project wel naar een site
     * verwijzen, bijvoorbeeld een foto van wat hij wil.
     */
    public function test_a_link_in_the_project_description_is_allowed(): void
    {
        $this->from('/offerte')
            ->post('/!/forms/offerte', $this->quote(['project' => 'Zoals op https://www.winsol.eu/nl/voordeuren']))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->stored);
    }

    /**
     * @return array<string, array{string, string, array<string, string>}>
     */
    public static function realCustomers(): array
    {
        return [
            'herstelling in het Nederlands' => ['herstelling', '/service', [
                'name' => 'Sofie Van den Broeck-D’Hondt',
                'address' => 'Sint-Jan-Baptiststraat 3 bus 2, 9000 Gent',
                'phone' => '09/123.45.67',
                'problem' => 'De lamellen van onze zonwering (± 4 m breed) blijven halverwege steken.',
            ]],
            'herstelling in het Frans' => ['herstelling', '/fr/service', [
                'name' => 'Hélène Lefèvre',
                'address' => 'Rue de l’Église 14, 1420 Braine-l’Alleud',
                'phone' => '+33 6 12 34 56 78',
                'problem' => 'Le volet ne descend plus, le moteur fait un bruit étrange depuis l’été.',
            ]],
            'herstelling in het Engels' => ['herstelling', '/en/service', [
                'name' => 'John O’Neill',
                'address' => 'Avenue Louise 231, 1050 Brussels',
                'phone' => '0032 2 123 45 67',
                'problem' => 'The awning won’t retract after the storm last week.',
            ]],
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    #[DataProvider('realCustomers')]
    public function test_a_real_customer_gets_through(string $form, string $page, array $values): void
    {
        $this->from($page)->post("/!/forms/{$form}", $this->repair($values))->assertSessionHasNoErrors();

        $this->assertNotNull($this->stored, 'Een echte klant werd geweigerd.');
    }

    public function test_a_real_quote_request_with_a_french_project_gets_through(): void
    {
        $this->from('/fr/devis')
            ->post('/!/forms/offerte', $this->quote([
                'products' => [$this->rangeSlug('fr')],
                'project' => 'Une porte d’entrée en aluminium, livraison avant l’hiver.',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->stored);
    }

    /**
     * Een bot vult het formulier in een fractie van een seconde in. De
     * laadtijd komt uit het formulier zelf, versleuteld, zodat een bot hem
     * niet kan terugzetten.
     */
    public function test_a_submission_within_three_seconds_of_loading_is_rejected(): void
    {
        Log::spy();
        $this->freezeTime();

        $token = $this->timerFrom($this->get('/offerte')->assertOk()->getContent());
        $this->travel(2)->seconds();

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote([FormTimer::FIELD => $token]))->assertSessionHasNoErrors();

        $this->assertRejectedAsSpam('offerte', 'te snel verstuurd');
    }

    /**
     * En dezelfde laadtijd volstaat zonder sessie: de laag werkt zonder
     * cookies.
     */
    public function test_a_submission_after_three_seconds_gets_through_without_a_session(): void
    {
        $this->freezeTime();

        $token = $this->timerFrom($this->get('/offerte')->assertOk()->getContent());
        $this->flushSession();
        $this->travel(4)->seconds();

        $this->from('/offerte')->post('/!/forms/offerte', $this->quote([FormTimer::FIELD => $token]))->assertSessionHasNoErrors();

        $this->assertNotNull($this->stored);
    }

    /**
     * Wie rechtstreeks naar het formulieradres post, heeft de pagina nooit
     * geladen. Het contactformulier staat op geen enkele pagina, dus daar
     * komt alleen nog dit soort verkeer binnen.
     */
    public function test_a_submission_without_a_load_time_is_rejected(): void
    {
        Log::spy();

        $payload = $this->quote();
        unset($payload[FormTimer::FIELD]);

        $this->from('/offerte')->post('/!/forms/offerte', $payload)->assertSessionHasNoErrors();

        $this->assertRejectedAsSpam('offerte', 'geen laadtijd');
    }

    /**
     * Na een foutmelding laadt de pagina opnieuw. Wie dan meteen opnieuw
     * verstuurt, heeft het formulier al lang ingevuld: de eerste laadtijd
     * blijft staan.
     */
    public function test_a_corrected_submission_keeps_its_first_load_time(): void
    {
        $this->freezeTime();

        $token = $this->timerFrom($this->get('/service')->assertOk()->getContent());
        $this->travel(30)->seconds();

        $errors = $this->errorsAfterPosting('herstelling', '/service', $this->repair(['installed' => 'rond 2010', FormTimer::FIELD => $token]));
        $this->assertArrayHasKey('installed', $errors);

        $retry = $this->timerFrom($this->get('/service')->assertOk()->getContent());
        $this->travel(1)->seconds();

        $this->from('/service')->post('/!/forms/herstelling', $this->repair(['installed' => '2010', FormTimer::FIELD => $retry]))->assertSessionHasNoErrors();

        $this->assertNotNull($this->stored, 'De verbeterde aanvraag werd als te snel geweigerd.');
    }

    /**
     * Anders laadt een bot één keer een pagina en gebruikt hij die laadtijd
     * voor altijd.
     */
    public function test_a_load_time_older_than_a_day_is_rejected(): void
    {
        Log::spy();

        $this->from('/offerte')
            ->post('/!/forms/offerte', $this->quote([FormTimer::FIELD => FormTimer::stamp(now()->subDay()->subMinute())]))
            ->assertSessionHasNoErrors();

        $this->assertRejectedAsSpam('offerte', 'laadtijd verlopen');
    }

    public function test_a_load_time_from_yesterday_evening_still_counts(): void
    {
        $this->from('/offerte')
            ->post('/!/forms/offerte', $this->quote([FormTimer::FIELD => FormTimer::stamp(now()->subHours(20))]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->stored);
    }

    public function test_a_direct_post_to_the_contact_form_is_rejected(): void
    {
        Log::spy();

        $this->from('/')
            ->post('/!/forms/contact', ['name' => 'Bot', 'email' => 'bot@voorbeeld.be', 'message' => 'Bonus'])
            ->assertSessionHasNoErrors();

        $this->assertRejectedAsSpam('contact', 'geen laadtijd');
    }

    public function test_a_forged_load_time_is_rejected(): void
    {
        Log::spy();

        $this->from('/offerte')
            ->post('/!/forms/offerte', $this->quote([FormTimer::FIELD => (string) now()->subHour()->getTimestamp()]))
            ->assertSessionHasNoErrors();

        $this->assertRejectedAsSpam('offerte', 'geen laadtijd');
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
    public function test_every_form_carries_its_load_time(string $page): void
    {
        $this->assertNotNull($this->timerFrom($this->get($page)->assertOk()->getContent()), "Geen laadtijd op {$page}.");
    }

    /**
     * Op live komt de pagina uit de statische cache. Het formulier staat in
     * {{ nocache }}, dus de laadtijd moet bij elk bezoek opnieuw gezet worden
     * en niet bevriezen op het moment dat de pagina gecachet werd.
     */
    public function test_the_load_time_is_fresh_under_the_static_cache(): void
    {
        config(['cache.stores.static_cache' => ['driver' => 'array'], 'statamic.static_caching.strategy' => 'half']);
        $this->freezeTime();

        $first = $this->timerFrom($this->get('/offerte')->assertOk()->getContent());
        $this->travel(1)->minute();
        $second = $this->timerFrom($this->get('/offerte')->assertOk()->getContent());

        $this->assertSame(0, FormTimer::secondsSinceLoad($second));
        $this->assertSame(60, FormTimer::secondsSinceLoad($first));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidRepairNumbers(): array
    {
        $year = (string) now()->year;

        return [
            'jaartal te vroeg' => ['installed', '1949', 'Vul een jaartal in tussen 1950 en '.$year.'.'],
            'jaartal in de toekomst' => ['installed', (string) ($year + 1), 'Vul een jaartal in tussen 1950 en '.$year.'.'],
            'jaartal in woorden' => ['installed', 'ongeveer tien jaar', 'Vul een jaartal in tussen 1950 en '.$year.'.'],
            'verdieping in woorden' => ['floor', 'gelijkvloers', 'Vul de verdieping in als getal, 0 voor het gelijkvloers.'],
            'verdieping te hoog' => ['floor', '572661', 'Vul de verdieping in als getal, 0 voor het gelijkvloers.'],
            'verdieping te diep' => ['floor', '-3', 'Vul de verdieping in als getal, 0 voor het gelijkvloers.'],
        ];
    }

    #[DataProvider('invalidRepairNumbers')]
    public function test_an_invalid_year_or_floor_shows_an_error(string $field, string $value, string $message): void
    {
        $errors = $this->errorsAfterPosting('herstelling', '/service', $this->repair([$field => $value]));

        $this->assertSame($message, $errors[$field] ?? null);
    }

    public function test_the_year_and_floor_errors_speak_french_and_english(): void
    {
        $year = now()->year;

        $fr = $this->errorsAfterPosting('herstelling', '/fr/service', $this->repair(['installed' => '1900', 'floor' => 'rez']));
        $this->assertSame("Indiquez une année entre 1950 et {$year}.", $fr['installed']);
        $this->assertSame('Indiquez l’étage en chiffres, 0 pour le rez-de-chaussée.', $fr['floor']);

        $en = $this->errorsAfterPosting('herstelling', '/en/service', $this->repair(['installed' => '1900', 'floor' => 'ground']));
        $this->assertSame("Enter a year between 1950 and {$year}.", $en['installed']);
        $this->assertSame('Enter the floor as a number, 0 for the ground floor.', $en['floor']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validRepairNumbers(): array
    {
        return [
            'eerste jaartal' => ['installed', '1950'],
            'dit jaar' => ['installed', (string) now()->year],
            'gelijkvloers' => ['floor', '0'],
            'kelder' => ['floor', '-2'],
            'hoogste verdieping' => ['floor', '30'],
        ];
    }

    #[DataProvider('validRepairNumbers')]
    public function test_a_valid_year_or_floor_passes(string $field, string $value): void
    {
        $this->from('/service')->post('/!/forms/herstelling', $this->repair([$field => $value]))->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function plausiblePhoneNumbers(): array
    {
        return [
            'gsm' => ['0470 12 34 56'],
            'gsm internationaal' => ['+32 470 12 34 56'],
            'vast nummer met punten' => ['02/123.45.67'],
            'met (0)' => ['+32 (0)470 12 34 56'],
            'met 00' => ['0032 470 123456'],
            'Frankrijk' => ['+33 6 12 34 56 78'],
            'Nederland' => ['06-12345678'],
            'harde spaties uit een handtekening' => ["0470\u{00A0}12\u{00A0}34\u{00A0}56"],
        ];
    }

    #[DataProvider('plausiblePhoneNumbers')]
    public function test_a_plausible_phone_number_passes(string $phone): void
    {
        $this->from('/offerte')->post('/!/forms/offerte', $this->quote(['phone' => $phone]))->assertSessionHasNoErrors();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function implausiblePhoneNumbers(): array
    {
        return [
            'elf cijfers zonder landcode' => ['89123456789'],
            'te kort' => ['12345'],
            'te lang' => ['+32 470 12 34 56 78 90 12'],
            'letters' => ['bel me maar'],
            'een link' => ['https://casino-vulkan.example'],
        ];
    }

    /**
     * Zelfde regel op elk formulier met een telefoonveld.
     *
     * @return array<string, array{string, string}>
     */
    public static function formsWithAPhone(): array
    {
        return [
            'offerte' => ['offerte', '/offerte'],
            'herstelling' => ['herstelling', '/service'],
            'brochure' => ['brochure', '/brochures'],
        ];
    }

    #[DataProvider('implausiblePhoneNumbers')]
    public function test_an_implausible_phone_number_shows_an_error(string $phone): void
    {
        $errors = $this->errorsAfterPosting('offerte', '/offerte', $this->quote(['phone' => $phone]));

        $this->assertSame('Vul een geldig telefoonnummer in, bijvoorbeeld 0470 12 34 56.', $errors['phone'] ?? null);
    }

    #[DataProvider('formsWithAPhone')]
    public function test_every_form_with_a_phone_checks_it(string $form, string $page): void
    {
        $errors = $this->errorsAfterPosting($form, $page, ['phone' => '89123456789']);

        $this->assertArrayHasKey('phone', $errors);
    }

    private function timerFrom(string $html): ?string
    {
        preg_match('/name="'.preg_quote(FormTimer::FIELD, '/').'" value="([^"]+)"/', $html, $match);

        return isset($match[1]) ? html_entity_decode($match[1]) : null;
    }
}
