<?php

namespace Tests\Feature\Forms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Form;
use Tests\TestCase;

/**
 * De foutmelding staat onder het veld waar ze over gaat, dus ze noemt het veld
 * niet opnieuw: ze zegt wat er mis is en wat je moet doen. Met de veldnaam
 * erin werd het "The Vertel kort over je project field is required." op de
 * Nederlandse site, want daar ontbrak lang/nl/validation.php.
 */
class FormErrorMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2_private');

        config(['services.recaptcha.api_key' => null, 'services.recaptcha.project_id' => null, 'services.recaptcha.site_key' => null]);
    }

    /**
     * @return array<string, array{array<string, string>, array<string, string>}>
     */
    public static function talen(): array
    {
        return [
            'nl' => [['offerte' => '/offerte', 'brochure' => '/brochures', 'herstelling' => '/service'], [
                'leeg' => 'Vul dit veld in.',
                'email' => 'Vul een geldig e-mailadres in, bijvoorbeeld naam@voorbeeld.be.',
                'akkoord' => 'Vink dit vakje aan om verder te gaan.',
                'keuze' => 'Maak een keuze uit de lijst.',
                'producten' => 'Kies minstens één product.',
                'brochures' => 'Kies minstens één brochure.',
                'bestandstype' => 'Dit bestandstype kan niet mee. Kies een foto of een pdf.',
                'te_groot' => 'Dit bestand is te groot. Kies een kleiner bestand.',
                'te_veel' => 'Je koos te veel bestanden. Er kunnen er hoogstens 5 mee.',
                'niet_aangekomen' => 'Dit bestand kwam niet aan. Kies een kleiner bestand of probeer het opnieuw.',
                'straat' => 'Vul je straat in.',
                'huisnummer' => 'Vul je huisnummer in.',
                'postcode' => 'Vul je postcode in.',
                'gemeente' => 'Vul je gemeente in.',
            ]],
            'fr' => [['offerte' => '/fr/devis', 'brochure' => '/fr/brochures', 'herstelling' => '/fr/service'], [
                'leeg' => 'Remplissez ce champ.',
                'email' => 'Saisissez une adresse e-mail valide, par exemple nom@exemple.be.',
                'akkoord' => 'Cochez cette case pour continuer.',
                'keuze' => 'Faites un choix dans la liste.',
                'producten' => 'Choisissez au moins un produit.',
                'brochures' => 'Choisissez au moins une brochure.',
                'bestandstype' => 'Ce type de fichier n’est pas accepté. Choisissez une photo ou un PDF.',
                'te_groot' => 'Ce fichier est trop lourd. Choisissez un fichier plus léger.',
                'te_veel' => 'Vous avez choisi trop de fichiers. 5 au maximum.',
                'niet_aangekomen' => 'Ce fichier n’est pas arrivé. Choisissez un fichier plus léger ou réessayez.',
                'straat' => 'Indiquez votre rue.',
                'huisnummer' => 'Indiquez votre numéro.',
                'postcode' => 'Indiquez votre code postal.',
                'gemeente' => 'Indiquez votre commune.',
            ]],
            'en' => [['offerte' => '/en/quote', 'brochure' => '/en/brochures', 'herstelling' => '/en/service'], [
                'leeg' => 'Please fill in this field.',
                'email' => 'Enter a valid email address, for example name@example.be.',
                'akkoord' => 'Tick this box to continue.',
                'keuze' => 'Choose an option from the list.',
                'producten' => 'Choose at least one product.',
                'brochures' => 'Choose at least one brochure.',
                'bestandstype' => 'This file type can’t be sent. Choose a photo or a PDF.',
                'te_groot' => 'This file is too large. Choose a smaller file.',
                'te_veel' => 'You picked too many files. 5 at most.',
                'niet_aangekomen' => 'This file didn’t come through. Choose a smaller file or try again.',
                'straat' => 'Enter your street.',
                'huisnummer' => 'Enter your house number.',
                'postcode' => 'Enter your postcode.',
                'gemeente' => 'Enter your town.',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $invoer
     * @return array<string, string>
     */
    private function foutenNaVersturen(string $formulier, string $vanaf, array $invoer = []): array
    {
        $this->from($vanaf)
            ->withHeader('Content-Type', 'multipart/form-data')
            ->post('/!/forms/'.$formulier, $invoer);

        $fouten = session('errors')->getBag('form.'.$formulier)->toArray();

        return array_map(fn (array $meldingen): string => $meldingen[0], $fouten);
    }

    /**
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_an_empty_text_field_asks_to_fill_it_in(array $paginas, array $verwacht): void
    {
        $fouten = $this->foutenNaVersturen('offerte', $paginas['offerte']);

        $this->assertSame($verwacht['leeg'], $fouten['name']);
        $this->assertSame($verwacht['leeg'], $fouten['project']);
    }

    /**
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_a_malformed_email_address_shows_what_a_good_one_looks_like(array $paginas, array $verwacht): void
    {
        $fouten = $this->foutenNaVersturen('offerte', $paginas['offerte'], ['email' => 'naam.voorbeeld.be']);

        $this->assertSame($verwacht['email'], $fouten['email']);
    }

    /**
     * Op de Franse site stond hier eerst het Nederlandse label in de zin: de
     * akkoordregel heeft geen vertaalde `display`, alleen een eigen zin in
     * partials/gdprField.
     *
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_an_unticked_consent_box_asks_to_tick_it(array $paginas, array $verwacht): void
    {
        $fouten = $this->foutenNaVersturen('offerte', $paginas['offerte']);

        $this->assertSame($verwacht['akkoord'], $fouten['gdpr']);
    }

    /**
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_an_unchosen_dropdown_asks_for_a_choice(array $paginas, array $verwacht): void
    {
        $offerte = $this->foutenNaVersturen('offerte', $paginas['offerte']);
        $herstelling = $this->foutenNaVersturen('herstelling', $paginas['herstelling']);

        $this->assertSame($verwacht['keuze'], $offerte['location']);

        foreach (['product', 'is_winsol', 'facade', 'branch', 'warranty'] as $veld) {
            $this->assertSame($verwacht['keuze'], $herstelling[$veld], "Keuzelijst {$veld} op het herstelformulier.");
        }
    }

    /**
     * Een waarde die niet in de lijst staat, krijgt een gewone bezoeker nooit
     * te zien. Ze komt van een verouderde pagina of een geknoeide POST, en dan
     * is "kies opnieuw" nog altijd de juiste raad.
     *
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_a_choice_outside_the_list_asks_for_a_choice(array $paginas, array $verwacht): void
    {
        $fouten = $this->foutenNaVersturen('offerte', $paginas['offerte'], ['location' => 'winsol-nergens']);

        $this->assertSame($verwacht['keuze'], $fouten['location']);
    }

    /**
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_an_empty_choice_of_pills_asks_for_at_least_one(array $paginas, array $verwacht): void
    {
        $offerte = $this->foutenNaVersturen('offerte', $paginas['offerte']);
        $brochure = $this->foutenNaVersturen('brochure', $paginas['brochure']);

        $this->assertSame($verwacht['producten'], $offerte['products']);
        $this->assertSame($verwacht['brochures'], $brochure['brochures']);
    }

    /**
     * Meestal vangt form-feedback.js dit al op in de browser. Dit is wat de
     * server zegt als dat script niet liep. De grens zelf staat al onder de
     * dropzone, dus de melding herhaalt geen kilobytes.
     *
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_a_refused_attachment_says_what_to_choose_instead(array $paginas, array $verwacht): void
    {
        $verkeerdType = $this->foutenNaVersturen('offerte', $paginas['offerte'], [
            'attachment' => [UploadedFile::fake()->create('plan.exe', 10)],
        ]);
        $teGroot = $this->foutenNaVersturen('offerte', $paginas['offerte'], [
            'attachment' => [UploadedFile::fake()->create('plan.pdf', 10241, 'application/pdf')],
        ]);
        $teVeel = $this->foutenNaVersturen('offerte', $paginas['offerte'], [
            'attachment' => array_map(fn (int $i): UploadedFile => UploadedFile::fake()->image("foto{$i}.jpg"), range(1, 6)),
        ]);

        $this->assertSame($verwacht['bestandstype'], $verkeerdType['attachment']);
        $this->assertSame($verwacht['te_groot'], $teGroot['attachment']);
        $this->assertSame($verwacht['te_veel'], $teVeel['attachment']);
    }

    /**
     * PHP weigert het bestand nog voor Laravel het ziet, meestal omdat het
     * boven `upload_max_filesize` gaat. Laravel meldt dat per bestand, op
     * `attachment.0`, en op `attachment` zelf zakt het bestandstype erdoor,
     * want van een bestand dat niet aankwam (PHP levert het zonder pad aan)
     * valt geen type te raden. Die
     * tweede melding zou de bezoeker naar een ander bestandstype sturen, en
     * buiten een test gooit dat raden zelfs een exception.
     *
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_an_upload_php_refused_says_it_did_not_come_through(array $paginas, array $verwacht): void
    {
        $geweigerd = new UploadedFile('', 'foto.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $fouten = $this->foutenNaVersturen('offerte', $paginas['offerte'], ['attachment' => [$geweigerd]]);

        $this->assertSame($verwacht['niet_aangekomen'], $fouten['attachment']);
    }

    /**
     * Het geweigerde bestand mag de andere controles op het veld niet
     * uitschakelen.
     *
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_an_upload_php_refused_still_counts_towards_the_limit(array $paginas, array $verwacht): void
    {
        $bestanden = array_map(fn (int $i): UploadedFile => UploadedFile::fake()->image("foto{$i}.jpg"), range(1, 5));
        $bestanden[] = new UploadedFile('', 'foto6.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $fouten = $this->foutenNaVersturen('offerte', $paginas['offerte'], ['attachment' => $bestanden]);

        $this->assertSame($verwacht['te_veel'], $fouten['attachment']);
    }

    /**
     * Fujifilm-camera's bewaren HEIF als `.hif`. Het blueprint keurt de
     * inhoud goed, maar Statamic toetst daarnaast de extensie aan een eigen
     * lijst, en daar ontbrak `hif`.
     */
    public function test_a_hif_photo_is_not_refused(): void
    {
        $fouten = $this->foutenNaVersturen('offerte', '/offerte', [
            'attachment' => [UploadedFile::fake()->create('foto.hif', 10, 'image/heif')],
        ]);

        $this->assertArrayNotHasKey('attachment', $fouten);
        $this->assertArrayNotHasKey('attachment.0', $fouten);
    }

    /**
     * Het vangnet: elke melding die een leeg formulier oplevert, is een van de
     * zinnen hierboven. Een nieuw veld of een nieuwe regel die terugvalt op
     * Laravels standaardtekst, valt hier op. Net zo een fout op een sleutel
     * die het sjabloon niet toont, zoals `attachment.0`: die maakt het
     * formulier ongeldig zonder dat de bezoeker ziet waarom.
     *
     * @param  array<string, string>  $paginas
     * @param  array<string, string>  $verwacht
     */
    #[DataProvider('talen')]
    public function test_no_form_falls_back_to_a_default_laravel_message(array $paginas, array $verwacht): void
    {
        foreach ($paginas as $formulier => $pagina) {
            $fouten = $this->foutenNaVersturen($formulier, $pagina, [
                'email' => 'naam.voorbeeld.be',
                'attachment' => [UploadedFile::fake()->create('plan.exe', 10)],
                'photo' => [UploadedFile::fake()->create('foto.exe', 10)],
                'invoice' => [UploadedFile::fake()->create('factuur.exe', 10)],
            ]);
            $velden = Form::find($formulier)->blueprint()->fields()->all()->keys()->all();

            $this->assertNotEmpty($fouten, "Het lege {$formulier}formulier gaf geen enkele fout.");

            foreach ($fouten as $veld => $melding) {
                $this->assertContains($melding, $verwacht, "{$formulier}.{$veld} toont een onbekende melding.");
                $this->assertContains($veld, $velden, "{$formulier}.{$veld} is geen veld, dus het sjabloon toont die melding niet.");
            }
        }
    }
}
