<?php

namespace Tests\Feature\Sections;

use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Glide as GlideManager;

class RangeHeaderTest extends SectionTestCase
{
    /**
     * Zelfde opzet als tests/Feature/ImgTagTest.php.
     *
     * `$opaqueInset` zet een transparante rand van die breedte om een dekkend
     * midden, zoals de echte range-png's die hebben.
     */
    private function makeImageAsset(string $filename = 'pergolas.png', int $opaqueInset = 0): Asset
    {
        Storage::fake('r2');

        // De alpha-maten liggen in Statamic's glide-store, en die is een
        // bestandscache die de test overleeft. Zonder deze flush leest een
        // volgende test met hetzelfde assetpad de maten van de vorige.
        GlideManager::cacheStore()->flush();

        $container = AssetContainer::make('assets')->disk('r2')->title('Assets');
        $container->save();

        $image = imagecreatetruecolor(1200, 1200);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, 1199, 1199, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle(
            $image,
            $opaqueInset,
            $opaqueInset,
            1199 - $opaqueInset,
            1199 - $opaqueInset,
            imagecolorallocatealpha($image, 0, 0, 0, 0)
        );

        ob_start();
        imagepng($image);
        Storage::disk('r2')->put($filename, ob_get_clean());
        imagedestroy($image);

        $asset = $container->makeAsset($filename);
        $asset->save();

        return $asset;
    }

    public function test_renders_title_and_short_description(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'short_description' => 'Geniet het hele jaar van uw terras.',
            'long_description' => 'Deze hoort in de sectie eronder, niet in de header.',
        ]);

        $this->assertStringContainsString('data-header="range"', $html);
        $this->assertStringContainsString('Terrasoverkapping', $html);
        $this->assertStringContainsString('Geniet het hele jaar van uw terras.', $html);
        $this->assertStringNotContainsString('Deze hoort in de sectie eronder', $html);

        // Pin de layering-workaround (zie header.css): `.header-title` en
        // `.header-intro` declareren hun eigen `font-size` omdat een
        // `text-*`-utility op een `h1`/`p` niets doet (ongelaagde CSS wint
        // altijd). Zonder deze assertie zou het vervangen van deze classes
        // door bv. `text-display` alle 17 bestaande tests groen laten, terwijl
        // de tekst stilletjes van 76px naar 61px zakt.
        $this->assertStringContainsString('<h1 class="header-title">Terrasoverkapping</h1>', $html);
        $this->assertStringContainsString('<p class="header-intro">Geniet het hele jaar van uw terras.</p>', $html);
    }

    public function test_renders_the_watermark_inside_the_clipping_layer(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => '/img/pergolas.png',
        ]);

        // De kern van dit component: het watermerk wordt geklipt en de
        // range-png niet. Als die twee ooit in dezelfde box belanden, klopt
        // één van beide niet meer — vandaar dat de volgorde en nesting hier
        // expliciet worden vastgelegd.
        $clip = strpos($html, 'data-header-watermark');
        $media = strpos($html, 'data-header-media');

        $this->assertNotFalse($clip);
        $this->assertNotFalse($media);
        $this->assertLessThan($media, $clip, 'Het watermerk staat vóór de png in de markup.');

        // Het watermerk zit in een klippende wrapper, de png staat erbuiten.
        $this->assertMatchesRegularExpression(
            '/data-header-watermark[^>]*class="[^"]*overflow-hidden/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-header-media[^>]*class="[^"]*overflow-hidden/',
            $html
        );

        // De negatieve assertie hierboven bewijst alleen dat de media-div
        // niet klipt. Ze bewijst niet dat de sectie zelf niet klipt: zet
        // `overflow-hidden` op de <section> en de png wordt alsnog geklipt —
        // precies de regressie die dit component moet voorkomen — terwijl
        // bovenstaande assertie groen zou blijven. Vandaar deze extra check
        // op de sectie's eigen class-lijst.
        $this->assertMatchesRegularExpression(
            '/<section[^>]*data-header="range"[^>]*>/',
            $html
        );
        preg_match('/<section[^>]*data-header="range"[^>]*>/', $html, $sectionTag);
        $this->assertDoesNotMatchRegularExpression('/overflow-/', $sectionTag[0]);
    }

    /**
     * De box blijft vierkant en dus even groot als het bronvlak, maar lijnt
     * niet langer op dát vlak uit. Glide snijdt de transparante rand weg en de
     * box zet het product met `justify-end` tegen de tekstkolom, zodat elke
     * range even ver van de tekst staat.
     *
     * Het beeld krijgt via `--trim-width` het aandeel van het canvas terug dat
     * het innam — hier een inset van 200px op 1200, dus 800/1200 = 66,667%.
     * Dat is wat de onderlinge schaal tussen de ranges intact houdt: zonder die
     * breedte zou elk product zijn box vullen en werden de smalle beelden
     * ineens even groot als de brede.
     */
    public function test_the_media_box_aligns_on_the_trimmed_product(): void
    {
        config(['app.debug' => false]);

        // Een echt asset, geen url-string: `{{ img }}` moet hier de picture-tak
        // in. De passthrough-tak (absolute http-src) bouwt geen Glide-url, en
        // dan kan de `crop_focal`-assertie hieronder niet meer falen.
        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => $this->makeImageAsset(opaqueInset: 200),
        ]);

        $this->assertMatchesRegularExpression(
            '/data-header-media[^>]*class="[^"]*aspect-square/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-header-media[^>]*class="[^"]*lg:justify-end/',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/<img[^>]*class="[^"]*range-media-image/',
            $html
        );
        // Het dekkende midden is 800 van 1200 breed, dus 66,667%. De scan
        // bemonstert met een stap en rekt de gevonden rand daarna met die stap
        // naar buiten op — liever een paar pixel lucht laten staan dan het
        // product aansnijden. De uitkomst mag dus iets ruimer zijn, nooit
        // krapper.
        $this->assertMatchesRegularExpression('/<img[^>]*style="--trim-width: [\d.]+%"/', $html);
        preg_match('/--trim-width: ([\d.]+)%/', $html, $trimWidth);

        $this->assertGreaterThanOrEqual(66.667, (float) $trimWidth[1]);
        $this->assertLessThan(68.0, (float) $trimWidth[1]);

        // De trim moet ook echt in de Glide-url landen, anders krijgt de
        // browser het volle canvas terug en klopt `--trim-width` niet meer
        // met wat er staat.
        $this->assertStringContainsString('trim=1', $html);

        // Geen crop: een `ratio` op {{ img }} laat Img::glideUrl()
        // `fit('crop_focal')` zetten, en dat landt als `fit=crop` in de
        // Glide-url — de letterlijke string `crop_focal` haalt de output nooit.
        // Zonder ratio staat er helemaal geen `fit=` in.
        $this->assertStringNotContainsString('fit=', $html);
    }

    /**
     * Een beeld zonder transparante rand valt terug op de volle box. Zou de
     * tag ook dan `trim` meesturen, dan ontstond er een tweede Glide-variant
     * van een bewerking die niets doet.
     */
    public function test_an_image_without_transparent_padding_is_left_alone(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => $this->makeImageAsset(opaqueInset: 0),
        ]);

        $this->assertStringNotContainsString('trim=1', $html);
        $this->assertStringNotContainsString('--trim-width', $html);
    }

    /**
     * Het beeld steekt links voorbij de paginamarge het beeld uit. Dat kan
     * alleen als niets ertussen klipt — de sectie niet (dat pint de test
     * hierboven) en de container niet. Zonder deze assertie zou een
     * `overflow-hidden` op de container het beeld stilletjes bijsnijden op
     * exact de marge, wat er "bijna goed" uitziet.
     */
    public function test_the_media_bleeds_past_the_page_margin(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
            'image' => '/img/pergolas.png',
        ]);

        $this->assertMatchesRegularExpression(
            '/data-header-media[^>]*class="[^"]*lg:-ml-/',
            $html
        );

        // Precies één klippende laag in deze header, en dat is die van het
        // watermerk. Een `overflow-hidden` erbij — op de sectie, de container
        // of de media zelf — snijdt het beeld af op exact de marge, wat er
        // "bijna goed" uitziet en dus makkelijk ongemerkt binnenglipt.
        $this->assertSame(1, substr_count($html, 'overflow-'));
    }

    /**
     * De nav zweeft over deze header, maar blijft zwart: de achtergrond is
     * licht. Dat is precies waarom `floating` en `inverse` twee vlaggen zijn
     * — zie ProductHeaderTest voor de kant waar ze allebei aanstaan.
     *
     * Het lichte vlak loopt daardoor door achter de nav, dus de header moet
     * die hoogte zelf overslaan. Beide kanten staan hier samen: verdwijnt de
     * ene, dan klopt de andere niet meer.
     */
    public function test_the_layout_floats_a_dark_navigation_over_this_header(): void
    {
        $layout = file_get_contents(resource_path('views/layout.antlers.html'));

        $this->assertMatchesRegularExpression(
            '/nav_floats\s*=\s*nav_over_photo\s*\|\|\s*template == \'ranges\/show\'/',
            $layout
        );

        // Wél zwevend, niet inverse: `inverse` hangt alleen aan de foto-header.
        $this->assertStringNotContainsString("nav_over_photo = template == 'ranges/show'", $layout);

        config(['app.debug' => false]);
        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
        ]);

        $this->assertStringContainsString('header-under-nav', $html);
    }

    /**
     * `--nav-height` stuurt zowel de nav-hoogte als de padding van deze
     * header. Zou de nav zijn hoogte weer uit padding halen, dan schuift de
     * H1 onder de zwevende nav zonder dat één test rood wordt.
     */
    public function test_the_nav_height_variable_drives_both_sides(): void
    {
        $nav = file_get_contents(resource_path('views/partials/navigation.antlers.html'));
        $this->assertStringContainsString('nav-bar', $nav);

        $navCss = file_get_contents(resource_path('css/components/navigation.css'));
        $this->assertMatchesRegularExpression('/@utility nav-bar \{\s*height: var\(--nav-height\);/', $navCss);

        $headerCss = file_get_contents(resource_path('css/components/header.css'));
        $this->assertMatchesRegularExpression(
            '/@utility header-under-nav \{\s*padding-top: calc\(var\(--nav-height\)/',
            $headerCss
        );

        $this->assertStringContainsString(
            "@import './components/navigation.css';",
            file_get_contents(resource_path('css/site.css'))
        );
    }

    public function test_omits_the_image_wrapper_without_an_image(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="headers/range" }}', [
            'title' => 'Terrasoverkapping',
        ]);

        $this->assertStringNotContainsString('data-header-media', $html);

        // Het watermerk hangt niet aan `image` en blijft de header dragen.
        $this->assertStringContainsString('data-header-watermark', $html);
    }

    public function test_ranges_render_through_their_own_template(): void
    {
        $this->assertFileExists(resource_path('views/ranges/show.antlers.html'));

        $yaml = file_get_contents(base_path('content/collections/ranges.yaml'));
        $this->assertStringContainsString('template: ranges/show', $yaml);

        $view = file_get_contents(resource_path('views/ranges/show.antlers.html'));
        $this->assertStringContainsString('headers/range', $view);
        $this->assertStringContainsString('pageBuilder', $view);
    }
}
