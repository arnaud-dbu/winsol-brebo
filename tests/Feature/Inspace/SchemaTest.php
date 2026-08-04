<?php

namespace Tests\Feature\Inspace;

use App\Inspace\PayloadValidator;
use Illuminate\Validation\ValidationException;
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
            ['bedrijfsnieuws', 'events', 'producten', 'realisaties', 'showroom'],
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

    /**
     * Het vangnet voor de bug die deze fixronde repareert: `GET /schema` en
     * `PayloadValidator` lazen ieder hun eigen idee van "verplicht bij het
     * aanmaken" (blueprint-`required` vs. hardgecodeerde regels). Beide lezen
     * nu uit `config('inspace.writable.articles.required_on_create')`, maar
     * deze test bewijst dat via gedrag, niet via de config zelf: voor elk
     * gemapt veld wordt het uit een verder volledige payload gehaald en
     * gecontroleerd of `PayloadValidator::validate()` dat ook echt als
     * ontbrekend-verplicht behandelt, precies zoals `/schema` claimt. Rood
     * geverifieerd door `required_on_create` tijdelijk op `[]` te zetten: de
     * assertie voor `title` faalde toen op "verwacht wél een fout, kreeg
     * geen fout". Teruggezet, weer groen.
     */
    public function test_schema_required_flags_agree_with_what_the_payload_validator_enforces(): void
    {
        $fields = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->json('collections.articles.fields');

        $full = [
            'title' => 'Consistentiecheck',
            'intro' => 'Intro.',
            'content' => [['type' => 'text', 'html' => '<p>Body.</p>']],
            'image' => 'irrelevant-voor-validatie',
            'theme' => 'realisaties',
            'slug' => 'consistentiecheck',
            'date' => '2026-08-04',
            'meta_title' => 'Meta',
            'meta_description' => 'Meta.',
            'meta_image' => 'irrelevant-voor-validatie',
            'seo_noindex' => false,
        ];

        $validator = app(PayloadValidator::class);

        foreach ($fields as $apiName => $description) {
            if (! array_key_exists($apiName, $full)) {
                // status/external_id zijn geen blueprint-gemapte velden en
                // hebben geen "compleet minus dit veld"-scenario om op te
                // toetsen; die blijven hier dus buiten beschouwing.
                continue;
            }

            $payload = $full;
            unset($payload[$apiName]);

            $threwForThisField = false;

            try {
                $validator->validate('articles', $payload, creating: true);
            } catch (ValidationException $e) {
                $threwForThisField = array_key_exists($apiName, $e->errors());
            }

            $this->assertSame(
                $description['required'],
                $threwForThisField,
                "Veld '{$apiName}': /schema zegt required={$this->boolLabel($description['required'])}, maar PayloadValidator ".
                ($threwForThisField ? 'geeft wél' : 'geeft geen').' een fout wanneer het ontbreekt.'
            );
        }
    }

    /**
     * Zelfde soort vangnet als hierboven, maar voor tekenlimieten in plaats
     * van verplichtheid: `title`, `slug` en `external_id` hebben geen
     * `character_limit` op hun blueprintveld, dus zonder de `max_lengths`-
     * config in `SchemaBuilder` zou `/schema` daar stil geen `max` tonen
     * terwijl `PayloadValidator` het wel afdwingt.
     */
    public function test_schema_reports_the_same_max_lengths_the_validator_enforces(): void
    {
        $fields = $this->withToken(self::TOKEN)
            ->getJson('/api/inspace/v1/schema')
            ->json('collections.articles.fields');

        $this->assertSame(255, $fields['title']['max']);
        $this->assertSame(200, $fields['slug']['max']);
        $this->assertSame(255, $fields['external_id']['max']);
        $this->assertSame(60, $fields['meta_title']['max']);
        $this->assertSame(160, $fields['meta_description']['max']);
    }

    private function boolLabel(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
