<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\Concerns\AssertsSiteVoice;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use AssertsSiteVoice;

    private function home()
    {
        return Entry::query()->where('collection', 'pages')->where('site', 'nl')->where('slug', 'home')->first();
    }

    /**
     * De page builder stond leeg, waardoor drie blokken uit het ontwerp
     * ontbraken: de aanbodslider, Pergola SO! en de CTA naar over ons.
     */
    public function test_the_page_builder_carries_the_three_blocks_from_the_design(): void
    {
        $types = collect($this->home()->get('page_builder'))->pluck('type')->all();

        $this->assertSame(['ranges', 'text_image', 'cta'], $types);
    }

    public function test_the_blocks_render_in_the_designed_order(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $ranges = strpos($html, 'data-section="ranges"');
        $textImage = strpos($html, 'data-section="text_image"');
        $cta = strpos($html, 'data-section="cta"');

        $this->assertNotFalse($ranges, 'De aanbodslider ontbreekt.');
        $this->assertLessThan($textImage, $ranges, 'De aanbodslider hoort boven Pergola SO! te staan');
        $this->assertLessThan($cta, $textImage, 'Pergola SO! hoort boven de CTA te staan');
    }

    /**
     * De `ranges`-set heeft geen `text` en geen `link`, dus `sectionHeader` viel
     * terug op de velden van de pagina zelf: de hero-intro en de knop "Ontdek
     * ons aanbod" verschenen een tweede keer boven de slider. Dezelfde
     * lekroute geldt voor products, features, cards, image_gallery en projects.
     */
    public function test_the_ranges_header_does_not_inherit_the_hero_intro_and_button(): void
    {
        $html = $this->get('/')->getContent();

        $intro = $this->home()->get('text');

        $this->assertSame(
            1,
            substr_count($html, $intro),
            'De hero-intro staat een tweede keer op de pagina.'
        );

        $this->assertSame(
            1,
            substr_count($html, 'Ontdek ons aanbod'),
            'De heroknop staat een tweede keer op de pagina.'
        );
    }

    /**
     * De over-ons-sectie wacht op een teamfoto die ter plaatse gemaakt wordt;
     * het Winsol-beeld dat er stond mag niet gebruikt worden. Die ene plek
     * staat bewust op de placeholder, zodat `winsol:image-gaps` ze oplijst;
     * een dummyfoto zou dat verbergen.
     */
    public function test_the_hero_and_the_blocks_carry_real_photos(): void
    {
        $home = $this->home();

        $images = collect($home->get('page_builder'))
            ->pluck('image')
            ->filter()
            ->push($home->get('image'));

        foreach ($images as $image) {
            $this->assertStringNotContainsString('dummy-images/', $image);
        }

        // De over-ons-sectie was de laatste open plek; die draagt sinds
        // 31-08 een echte showroomfoto. Blijft deze teller op nul, dan staat
        // er nergens op de homepage nog een placeholder.
        $this->assertSame(
            0,
            $images->filter(fn ($image) => str_starts_with($image, 'placeholder/'))->count(),
            'Het aantal open beeldplekken is veranderd.'
        );
    }

    /**
     * Brochures moeten makkelijker vindbaar zijn (Jimmy, 26-08): de
     * quicklinks-sectie sluit de homepage af mét de brochurekaart, die hier
     * kaal naar /brochures linkt omdat de homepage geen specifieke pdf heeft.
     */
    public function test_the_quicklinks_close_the_page_with_the_brochure_card(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-section="quicklinks"', $html);
        $this->assertStringContainsString('href="/brochures"', $html);
        $this->assertSame(3, substr_count($html, 'quicklink-card'));
    }

    public function test_the_copy_speaks_in_the_je_form_without_em_dashes(): void
    {
        $home = $this->home();

        $this->assertSpeaksSiteVoice($home->get('title').' '.$home->get('text'), 'hero');

        foreach ($home->get('value_proposition')['items'] as $item) {
            $this->assertSpeaksSiteVoice($item['title'].' '.$item['text'], "waarom {$item['id']}");
        }

        foreach ($home->get('page_builder') as $block) {
            $text = is_array($block['text'] ?? null)
                ? $this->flattenBard($block['text'])
                : ($block['text'] ?? '');

            $this->assertSpeaksSiteVoice(($block['title'] ?? '').' '.$text, "blok {$block['id']}");
        }
    }

    /**
     * De locatiesectie stond onder de page builder terwijl het ontwerp hem op
     * /home niet toont. Hij hoort op de contactpagina en op de rangepagina's.
     */
    public function test_the_page_does_not_show_the_locations_section(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('data-section="locations"', $html);
    }

    /**
     * Figma schrijft "Lees ons overhaal" op de knop van de over-ons-CTA.
     */
    public function test_the_over_ons_cta_button_is_spelled_correctly(): void
    {
        $cta = collect($this->home()->get('page_builder'))->firstWhere('type', 'cta');

        $this->assertSame('Lees ons verhaal', $cta['link'][0]['label']);
    }
}
