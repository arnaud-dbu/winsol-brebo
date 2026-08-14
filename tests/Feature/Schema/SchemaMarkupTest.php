<?php

namespace Tests\Feature\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SchemaMarkupTest extends TestCase
{
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

    public function test_the_placeholder_socials_never_reach_the_output(): void
    {
        $this->assertStringNotContainsString('test.be', $this->get('/')->getContent());
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
