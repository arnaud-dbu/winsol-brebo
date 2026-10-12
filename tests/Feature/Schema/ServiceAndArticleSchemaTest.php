<?php

namespace Tests\Feature\Schema;

use App\Schema\ArticleSchema;
use App\Schema\OrganizationSchema;
use App\Schema\ServiceSchema;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class ServiceAndArticleSchemaTest extends TestCase
{
    use CreatesTemporaryContent;

    protected function setUp(): void
    {
        parent::setUp();

        // De acht nieuwsartikels stonden tot 05-09-2026 in de content en zijn
        // toen als testdata verwijderd. Deze tests toetsen de weergave ervan,
        // dus zetten ze de fixtures zelf neer.
        $this->seedArticles();
    }

    public function test_a_product_becomes_a_service_that_names_its_area(): void
    {
        $entry = Entry::findByUri('/aanbod/rolluiken/inbouwrolluiken');
        $this->assertNotNull($entry, 'Verwacht de entry /aanbod/rolluiken/inbouwrolluiken (Inbouwrolluiken).');

        $node = ServiceSchema::node($entry);

        $this->assertSame('Service', $node['@type']);
        $this->assertSame('Inbouwrolluiken', $node['name']);
        $this->assertSame('Plaatsing van Inbouwrolluiken', $node['serviceType']);
        $this->assertSame(OrganizationSchema::id(), $node['provider']['@id']);
        $this->assertContains('Dilbeek', $node['areaServed']);
        $this->assertContains('Aartselaar', $node['areaServed']);
        $this->assertContains('Sint-Pieters-Leeuw', $node['areaServed']);
    }

    public function test_a_range_becomes_a_service_too(): void
    {
        $entry = Entry::findByUri('/aanbod/rolluiken');
        $this->assertNotNull($entry, 'Verwacht de entry /aanbod/rolluiken (Rolluiken).');

        $node = ServiceSchema::node($entry);

        $this->assertSame('Service', $node['@type']);
        $this->assertSame('Rolluiken', $node['name']);
    }

    public function test_an_article_carries_its_publication_date(): void
    {
        $entry = Entry::query()->where('collection', 'articles')->where('site', 'nl')->first();
        $this->assertNotNull($entry, 'Verwacht minstens één entry in de collectie articles.');

        $node = ArticleSchema::node($entry);

        $this->assertSame('Article', $node['@type']);
        $this->assertSame((string) $entry->get('title'), $node['headline']);
        $this->assertNotEmpty($node['datePublished']);
        $this->assertSame(OrganizationSchema::id(), $node['publisher']['@id']);
    }

    /**
     * `image` en `dateModified` bestaan vandaag al op artikelentries (de
     * page-header-imagefieldset resp. `updated_at`), dus horen ze in de
     * node te staan zonder dat er een blueprint bij hoefde te veranderen.
     */
    public function test_an_article_carries_its_image_and_modification_date(): void
    {
        $entry = Entry::query()->where('collection', 'articles')->where('site', 'nl')->first();
        $this->assertNotNull($entry, 'Verwacht minstens één entry in de collectie articles.');
        $this->assertNotEmpty($entry->get('image'), 'Verwacht een gevulde image op deze fixture-entry.');

        $node = ArticleSchema::node($entry);

        $this->assertNotEmpty($node['image']);
        $this->assertStringStartsWith('http', $node['image']);
        $this->assertNotEmpty($node['dateModified']);
    }

    /**
     * Tot de klant een naam aanlevert, staat het veld leeg. Dan is de
     * organisatie de auteur, en geen lege Person.
     */
    public function test_an_article_without_an_author_is_written_by_the_organization(): void
    {
        $entry = Entry::query()->where('collection', 'articles')->where('site', 'nl')->first();
        $entry->set('author_name', null);

        $node = ArticleSchema::node($entry);

        $this->assertSame(['@id' => OrganizationSchema::id()], $node['author'] ?? null);
    }

    public function test_an_article_with_an_author_names_that_person(): void
    {
        $entry = Entry::query()->where('collection', 'articles')->where('site', 'nl')->first();
        $entry->set('author_name', '  Jan Peeters ');

        $node = ArticleSchema::node($entry);

        $this->assertSame(['@type' => 'Person', 'name' => 'Jan Peeters'], $node['author'] ?? null);
    }

    /**
     * Niet `author`: een veld met die handle vult Statamic bij het aanmaken
     * met de ingelogde gebruiker, en dan zou die als auteur in de markup staan.
     */
    public function test_the_article_blueprint_offers_the_author_name_field(): void
    {
        $blueprint = Collection::findByHandle('articles')->entryBlueprint();

        $this->assertTrue($blueprint->hasField('author_name'));
        $this->assertFalse($blueprint->hasField('author'));
    }

    public function test_a_null_entry_yields_no_node(): void
    {
        $this->assertNull(ServiceSchema::node(null));
        $this->assertNull(ArticleSchema::node(null));
    }
}
