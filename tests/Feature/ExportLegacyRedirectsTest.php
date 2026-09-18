<?php

namespace Tests\Feature;

use App\Services\LegacyRedirect;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Het geëxporteerde bestand draait op de oude server, buiten Laravel. Het
 * wordt hier getoetst zoals het daar draait: via HTTP, voor een front
 * controller die de oude pagina serveert als het bestand niets doet.
 *
 * `header()` doet niets in de CLI, dus een gewone `require` in de test kan
 * de omleiding niet zien.
 */
class ExportLegacyRedirectsTest extends TestCase
{
    private const OLD_PAGE = 'OUDE PAGINA';

    private static ?Process $server = null;

    private static string $base;

    private static string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$server !== null) {
            return;
        }

        self::$directory = sys_get_temp_dir().'/legacy-redirects-'.getmypid();
        @mkdir(self::$directory);

        $this->artisan('winsol:export-legacy-redirects', ['path' => self::$directory.'/winsol-legacy-redirects.php'])
            ->assertSuccessful();

        file_put_contents(
            self::$directory.'/index.php',
            "<?php\nrequire __DIR__ . '/winsol-legacy-redirects.php';\necho '".self::OLD_PAGE."';\n"
        );

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$base = 'http://127.0.0.1:'.$port;
        self::$server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, self::$directory.'/index.php']);
        self::$server->disableOutput();
        self::$server->start();

        $deadline = microtime(true) + 5;

        while (! @fsockopen('127.0.0.1', $port) && microtime(true) < $deadline) {
            usleep(20_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;

        array_map('unlink', glob(self::$directory.'/*'));
        @rmdir(self::$directory);

        parent::tearDownAfterClass();
    }

    /**
     * @return array{status: int, location: ?string, body: string}
     */
    private function fetch(string $path, string $method = 'GET'): array
    {
        $body = file_get_contents(self::$base.$path, false, stream_context_create([
            'http' => ['method' => $method, 'follow_location' => 0, 'ignore_errors' => true],
        ]));

        preg_match('#^HTTP/\S+ (\d+)#', $http_response_header[0], $status);
        $location = null;

        foreach ($http_response_header as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }

        return ['status' => (int) $status[1], 'location' => $location, 'body' => $body];
    }

    private function assertRedirectsTo(string $old, string $new): void
    {
        $response = $this->fetch($old);

        $this->assertSame(301, $response['status'], "{$old} wordt niet omgeleid.");
        $this->assertSame('https://winsol-brebo.be'.$new, $response['location'], "{$old} gaat naar de verkeerde plek.");
    }

    private function assertLeftAlone(string $old, string $method = 'GET'): void
    {
        $response = $this->fetch($old, $method);

        $this->assertSame(200, $response['status']);
        $this->assertNull($response['location'], "{$old} wordt omgeleid terwijl het met rust gelaten moet worden.");
        $this->assertSame(self::OLD_PAGE, $response['body']);
    }

    /**
     * Zonder klim bleef elk adres dat niet in de sitemap stond op de oude
     * site staan, ook het herstelformulier onder Contact. Klanten vroegen daar
     * nog weken herstellingen aan bij de oude site.
     */
    public function test_an_unlisted_address_climbs_to_the_nearest_parent(): void
    {
        $this->assertRedirectsTo('/nl/Contact/Onbekend/', '/contact');
        $this->assertRedirectsTo('/nl/Ons-aanbod/Rolluiken/Solarbox/', '/aanbod/rolluiken');
        $this->assertRedirectsTo('/fr/Notre-gamme/Couvertures-de-terrasse/Pergola-SO-/', '/fr/gamme/couverture-de-terrasse');
    }

    public function test_the_repair_form_and_its_short_links_land_on_the_repair_form(): void
    {
        $this->assertRedirectsTo('/forms/herstellingaanvragen', '/service');
        $this->assertRedirectsTo('/nl/Formulieren/Herstellingsformulier-Winsol/', '/service');
        $this->assertRedirectsTo('/nl/Contact/Herstelling-aanvragen/', '/service');
        $this->assertRedirectsTo('/forms/reparation', '/fr/service');
        $this->assertRedirectsTo('/fr/Contact/Demander-une-reparation/', '/fr/service');
    }

    /**
     * Wat nergens op uitkomt, laat het bestand aan het oude CMS over. De klim
     * mag daarbij niet op een homepage belanden: `/nl` staat als regel in de
     * tabel en zou anders alles onder `/nl/` naar de root sturen.
     */
    public function test_an_address_without_a_parent_is_left_to_the_old_site(): void
    {
        $this->assertLeftAlone('/cms/');
        $this->assertLeftAlone('/documents/brochure.pdf');
        $this->assertLeftAlone('/nl/Iets-dat-niet-bestaat/');
    }

    public function test_a_post_is_left_alone(): void
    {
        $this->assertLeftAlone('/nl/Contact/Herstelling-aanvragen/', 'POST');
    }

    public function test_the_query_string_survives(): void
    {
        $this->assertRedirectsTo('/nl/Contact/?utm_source=mail&b=1', '/contact?utm_source=mail&b=1');
    }

    public function test_a_brochure_download_lands_on_the_brochure_page(): void
    {
        $this->assertRedirectsTo('/fr/Notre-gamme/Volets-roulants/Download-brochure-rolluiken-fr/', '/fr/brochures');
    }

    /**
     * Het bestand en de middleware moeten voor elk oud adres hetzelfde doen.
     * Het bestand draait nu op de oude server; de middleware neemt het over
     * zodra het domein verhuist.
     */
    public function test_every_address_in_the_old_sitemap_goes_where_the_app_sends_it(): void
    {
        $sitemap = file(__DIR__.'/../fixtures/legacy-sitemap.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($sitemap as $old) {
            $this->assertRedirectsTo($old, LegacyRedirect::destinationFor(LegacyRedirect::normalise($old)));
        }
    }
}
