<?php

namespace Tests\Feature\Content;

use Statamic\Facades\GlobalSet;
use Tests\TestCase;

/**
 * De SEO-global belooft vier sitebrede instellingen. Meta Title en de
 * noindex-schakelaar stonden wel in het blueprint maar werden nergens in
 * `partials/seo.antlers.html` gelezen: invullen deed niets en er kwam geen
 * enkel signaal dat het niet werkte. Deze test bewaakt dat alle vier de velden
 * aangesloten blijven.
 */
class SeoGlobalFallbacksTest extends TestCase
{
    /**
     * De global wordt weggeschreven naar `content/globals/nl/seo.yaml`, dus de
     * oorspronkelijke waarden gaan hier op de terugweg weer terug. Zonder dat
     * herstel lekt de testwaarde naar de werkkopie en staat ze in de volgende
     * commit.
     *
     * @param  array<string, mixed>  $data
     */
    private function withSeoGlobal(array $data): void
    {
        $variables = GlobalSet::findByHandle('seo')->inDefaultSite();
        $original = $variables->data()->all();

        $this->beforeApplicationDestroyed(function () use ($original): void {
            GlobalSet::findByHandle('seo')->inDefaultSite()->data($original)->save();
        });

        $variables->data($data)->save();
    }

    /**
     * De partial zet titel en sitenaam op aparte regels, dus vergelijken op de
     * ruwe HTML struikelt over inspringing en newlines.
     */
    private function titleOf(string $uri): string
    {
        preg_match('/<title>(.*?)<\/title>/s', $this->get($uri)->getContent(), $match);

        return trim(preg_replace('/\s+/', ' ', $match[1] ?? ''));
    }

    public function test_the_global_meta_title_never_overrules_a_page_that_has_its_own_title(): void
    {
        $this->withSeoGlobal(['meta_title' => 'Sitebrede reservetitel']);

        $this->assertSame('Screens voor ramen | Winsol Brebo', $this->titleOf('/aanbod/zonwering/screens'));
    }

    /**
     * De 404-pagina rendert buiten elke entry, dus `title` is daar leeg. Dat is
     * precies het geval waarvoor de global bedoeld is: zonder fallback staat er
     * enkel nog de sitenaam in het title-element.
     */
    public function test_the_global_meta_title_carries_a_page_without_a_title_of_its_own(): void
    {
        $this->withSeoGlobal(['meta_title' => 'Sitebrede reservetitel']);

        $this->assertSame('Sitebrede reservetitel | Winsol Brebo', $this->titleOf('/deze-pagina-bestaat-niet'));
    }

    public function test_the_global_noindex_hides_every_page_at_once(): void
    {
        $this->withSeoGlobal(['seo_noindex' => true]);

        foreach (['/', '/aanbod/zonwering/screens'] as $uri) {
            $this->assertStringContainsString(
                'name="robots"',
                $this->get($uri)->getContent(),
                "Geen robots-tag op {$uri} terwijl de globale noindex aanstaat"
            );
        }
    }

    public function test_no_robots_tag_when_neither_the_page_nor_the_global_asks_for_one(): void
    {
        $this->withSeoGlobal([]);

        $this->assertStringNotContainsString('name="robots"', $this->get('/')->getContent());
    }

    public function test_the_global_meta_description_and_sharing_image_still_fall_through(): void
    {
        $this->withSeoGlobal([
            'meta_description' => 'Sitebrede reservebeschrijving',
            'meta_image' => 'realisatie-realisation_squaro-03.jpg',
        ]);

        $html = $this->get('/deze-pagina-bestaat-niet')->getContent();

        $this->assertStringContainsString('Sitebrede reservebeschrijving', $html);
        $this->assertStringContainsString('property="og:image"', $html);
    }
}
