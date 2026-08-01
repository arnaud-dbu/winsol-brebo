<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\Concerns\AssertsSiteVoice;
use Tests\TestCase;

class OverOnsPageTest extends TestCase
{
    use AssertsSiteVoice;

    private function overOns()
    {
        return Entry::query()->where('collection', 'pages')->where('slug', 'over-ons')->first();
    }

    /**
     * De pagina droeg de titel "About" en een lorem-ipsum in een `intro`-veld
     * dat geen enkele template uitleest.
     */
    public function test_the_entry_carries_its_own_title_and_template(): void
    {
        $entry = $this->overOns();

        $this->assertSame('Over ons', $entry->get('title'));
        $this->assertSame('over-ons', $entry->get('template'));
        $this->assertNull($entry->get('intro'), 'Het ongebruikte intro-veld staat er nog.');
    }

    public function test_the_page_builder_follows_the_design(): void
    {
        $types = collect($this->overOns()->get('page_builder'))->pluck('type')->all();

        $this->assertSame(['text_image', 'features', 'grid_cta', 'cta'], $types);
    }

    /**
     * Het ontwerp sluit deze pagina af met de locatiesectie, ná de CTA. Daarom
     * heeft over-ons een eigen template en niet `default`.
     */
    public function test_the_locations_section_closes_the_page(): void
    {
        $html = $this->get('/over-ons')->assertOk()->getContent();

        $cta = strpos($html, 'data-section="cta"');
        $locations = strpos($html, 'data-section="locations"');

        $this->assertNotFalse($locations, 'De locatiesectie ontbreekt.');
        $this->assertLessThan($locations, $cta, 'De locaties horen onder de CTA te staan');
    }

    public function test_the_grid_links_point_at_the_brand_and_at_the_mailbox(): void
    {
        $grid = collect($this->overOns()->get('page_builder'))->firstWhere('type', 'grid_cta')['grid'];

        $this->assertSame('winsol.eu', $grid[0]['link'][0]['url']);
        $this->assertTrue($grid[0]['link'][0]['new_tab'], 'Een link naar het merk hoort in een nieuw tabblad.');

        // Hetzelfde adres als in de globals; de open sollicitatie komt bij het
        // hoofdverkooppunt binnen.
        $this->assertSame('info@winsoldilbeek.be', $grid[1]['link'][0]['e_mail']);
    }

    /**
     * Twee beeldplekken wachten op een foto van het pand in Dilbeek, die niet
     * in de container zit. Ze staan bewust op de placeholder, zodat
     * `winsol:image-gaps` ze oplijst; een dummyfoto zou dat verbergen.
     */
    public function test_the_open_image_slots_use_the_placeholder_and_no_dummy(): void
    {
        $images = collect($this->overOns()->get('page_builder'))->pluck('image')->filter();

        foreach ($images as $image) {
            $this->assertStringNotContainsString('dummy-images/', $image);
        }

        $this->assertSame(
            2,
            $images->filter(fn ($image) => str_starts_with($image, 'placeholder/'))->count(),
            'Het aantal open beeldplekken is veranderd.'
        );
    }

    public function test_the_copy_speaks_in_the_je_form_without_em_dashes(): void
    {
        $entry = $this->overOns();

        $this->assertSpeaksSiteVoice($entry->get('title').' '.$entry->get('text'), 'intro');

        foreach ($entry->get('page_builder') as $block) {
            $text = is_array($block['text'] ?? null)
                ? $this->flattenBard($block['text'])
                : ($block['text'] ?? '');

            $this->assertSpeaksSiteVoice(($block['title'] ?? '').' '.$text, "blok {$block['id']}");

            foreach ($block['features'] ?? [] as $feature) {
                $this->assertSpeaksSiteVoice($feature['title'].' '.$feature['text'], "feature {$feature['id']}");
            }

            foreach ($block['grid'] ?? [] as $item) {
                $this->assertSpeaksSiteVoice($item['title'].' '.$item['text'], "grid {$item['id']}");
            }
        }
    }
}
