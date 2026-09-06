<?php

namespace Tests\Feature\Schema;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Antlers;
use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class SchemaMarkupTest extends TestCase
{
    use \Tests\Concerns\CreatesTemporaryContent;

    protected function setUp(): void
    {
        parent::setUp();

        // De acht nieuwsartikels stonden tot 05-09-2026 in de content en zijn
        // toen als testdata verwijderd. Deze tests toetsen de weergave ervan,
        // dus zetten ze de fixtures zelf neer.
        $this->seedArticles();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pages(): array
    {
        return [
            'homepage' => ['/'],
            'range' => ['/aanbod/rolluiken'],
            'product' => ['/aanbod/rolluiken/inbouwrolluiken'],
            'overzicht' => ['/aanbod'],
            'contact' => ['/contact'],
            'nieuws' => ['/nieuws'],
        ];
    }

    /**
     * De kern van dit ontwerp: op élk paginatype moet het blok geldige JSON
     * zijn. Dit is de enige test die een escapingfout kan betrappen.
     */
    #[DataProvider('pages')]
    public function test_the_json_ld_block_parses_as_valid_json(string $path): void
    {
        $graph = $this->graphFrom($path);

        $this->assertNotSame([], $graph, "Geen @graph gevonden op {$path}.");
    }

    public function test_the_homepage_carries_the_organization_and_three_showrooms(): void
    {
        $types = array_column($this->graphFrom('/'), '@type');

        $this->assertContains('Organization', $types);
        $this->assertSame(3, count(array_filter($types, fn ($t) => $t === 'LocalBusiness')));
    }

    public function test_a_product_page_carries_a_service_and_a_breadcrumb(): void
    {
        $types = array_column($this->graphFrom('/aanbod/rolluiken/inbouwrolluiken'), '@type');

        $this->assertContains('Service', $types);
        $this->assertContains('BreadcrumbList', $types);
    }

    public function test_an_article_page_carries_an_article_with_a_date(): void
    {
        $graph = $this->graphFrom('/nieuws/winsol-wint-zijn-vijfde-red-dot-award');

        $article = collect($graph)->firstWhere('@type', 'Article');

        $this->assertNotNull($article, 'Een nieuwsartikel hoort een Article-node te hebben.');
        $this->assertNotEmpty($article['datePublished']);
    }

    /**
     * Een @id-verwijzing die nergens heen wijst maakt de graph waardeloos:
     * dan zijn het alsnog losse fragmenten in plaats van één entiteit.
     */
    public function test_every_id_reference_resolves_within_the_graph(): void
    {
        $graph = $this->graphFrom('/aanbod/rolluiken/inbouwrolluiken');
        $known = array_column($graph, '@id');

        foreach ($graph as $node) {
            foreach (['provider', 'parentOrganization', 'publisher'] as $key) {
                if (isset($node[$key]['@id'])) {
                    $this->assertContains(
                        $node[$key]['@id'],
                        $known,
                        "{$node['@type']}.{$key} wijst naar een @id dat niet in de graph staat.",
                    );
                }
            }
        }
    }

    /**
     * `test.be` staat er sowieso nooit in de output, ook zonder deze functie:
     * dat kan geen kapotte implementatie betrappen. Wat wél falsifieerbaar
     * is: zolang de globals op placeholders staan, mag geen enkele node in de
     * graph een `sameAs`-sleutel dragen.
     */
    public function test_the_placeholder_socials_never_reach_the_output(): void
    {
        $globals = GlobalSet::findByHandle('globals')?->inCurrentSite();
        $socials = (array) ($globals?->get('socials') ?? []);

        $this->assertNotEmpty($socials, 'Verwacht placeholder-socials in de globals om dit te kunnen toetsen.');

        $graph = $this->graphFrom('/');

        foreach ($graph as $node) {
            $this->assertArrayNotHasKey(
                'sameAs',
                $node,
                "{$node['@type']} draagt sameAs terwijl de globals nog op placeholders staan.",
            );
        }
    }

    /**
     * De laag zit in de <head> van élke pagina: een gooiende bouwer mag
     * alleen het JSON-LD-blok kosten, nooit de rest van de pagina. Getest op
     * de tag zelf, niet via een volledige paginarequest — anders breekt ook
     * elk ander gebruik van `{{ globals:... }}` op de pagina mee, en toetst
     * de test niet meer specifiek de foutafhandeling van `Tags\Schema`.
     */
    public function test_a_throwing_builder_degrades_to_an_empty_string_instead_of_a_500(): void
    {
        GlobalSet::shouldReceive('findByHandle')->with('globals')->andThrow(new \RuntimeException('kapotte globals'));
        Log::shouldReceive('warning')->once()->with(\Mockery::pattern('/kapotte globals/'));

        $html = Antlers::parse('{{ schema }}', []);

        $this->assertSame('', trim((string) $html));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function graphFrom(string $path): array
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">#',
            $html,
            "Geen JSON-LD-blok op {$path}.",
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $decoded = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        return $decoded['@graph'] ?? [];
    }
}
