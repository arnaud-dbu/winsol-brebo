<?php

namespace Tests\Feature\Inspace;

use Tests\TestCase;

class SchemaTest extends TestCase
{
    private const TOKEN = 'test-token-abc';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inspace.tokens', ['test' => hash('sha256', self::TOKEN)]);
    }

    public function test_schema_describes_articles_as_writable(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->assertOk()
            ->assertJsonPath('collections.articles.writable', true)
            ->assertJsonPath('collections.articles.route', '/nieuws/{slug}')
            ->assertJsonPath('collections.articles.fields.title.required', true)
            ->assertJsonPath('collections.articles.fields.content.type', 'blocks')
            ->assertJsonPath('collections.articles.fields.content.writable_types', ['text'])
            ->assertJsonPath('collections.articles.fields.content.opaque_types', ['video']);
    }

    public function test_theme_values_come_live_from_the_taxonomy(): void
    {
        $response = $this->withToken(self::TOKEN)->getJson('/api/inspace/v1/schema');

        $values = $response->json('collections.articles.fields.theme.values');

        sort($values);

        $this->assertSame(
            ['energie-en-comfort', 'ramen-en-deuren', 'terrasoverkapping', 'zonwering'],
            $values,
            'De themawaarden moeten uit de taxonomie komen, niet uit config.'
        );
        $this->assertTrue($response->json('collections.articles.fields.theme.required'));
    }

    public function test_allowed_html_is_derived_from_the_bard_buttons(): void
    {
        $allowed = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->json('collections.articles.fields.content.allowed_html');

        $this->assertContains('h2', $allowed);
        $this->assertContains('img', $allowed);
        $this->assertContains('table', $allowed);
        $this->assertNotContains('h1', $allowed, 'h1 staat niet in de buttonconfig.');
        $this->assertNotContains('blockquote', $allowed);
    }
}
