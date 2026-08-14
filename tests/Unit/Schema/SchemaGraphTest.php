<?php

namespace Tests\Unit\Schema;

use App\Schema\SchemaGraph;
use PHPUnit\Framework\TestCase;

class SchemaGraphTest extends TestCase
{
    public function test_it_wraps_nodes_in_a_context_and_graph(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Organization', '@id' => 'https://x.test/#organization'])
            ->toJson();

        $decoded = json_decode($json, true);

        $this->assertSame('https://schema.org', $decoded['@context']);
        $this->assertCount(1, $decoded['@graph']);
    }

    public function test_nodes_with_the_same_id_are_deduplicated(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Organization', '@id' => 'https://x.test/#o'])
            ->add(['@type' => 'Organization', '@id' => 'https://x.test/#o'])
            ->toJson();

        $this->assertCount(1, json_decode($json, true)['@graph']);
    }

    public function test_null_nodes_are_ignored(): void
    {
        $this->assertTrue((new SchemaGraph)->add(null)->isEmpty());
    }

    public function test_empty_values_are_pruned_recursively(): void
    {
        $decoded = json_decode((new SchemaGraph)->add([
            '@type' => 'LocalBusiness',
            '@id' => 'https://x.test/#l',
            'name' => 'Winsol Dilbeek',
            'telephone' => '',
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => null],
            'sameAs' => [],
        ])->toJson(), true);

        $node = $decoded['@graph'][0];

        $this->assertSame('Winsol Dilbeek', $node['name']);
        $this->assertArrayNotHasKey('telephone', $node);
        $this->assertArrayNotHasKey('geo', $node);
        $this->assertArrayNotHasKey('sameAs', $node);
    }

    public function test_pruned_lists_keep_sequential_keys(): void
    {
        $decoded = json_decode((new SchemaGraph)->add([
            '@type' => 'Service',
            '@id' => 'https://x.test/#s',
            'areaServed' => ['Dilbeek', '', 'Aartselaar'],
        ])->toJson(), true);

        $this->assertSame(['Dilbeek', 'Aartselaar'], $decoded['@graph'][0]['areaServed']);
    }

    /**
     * Dit is de reden dat de graph in PHP gebouwd wordt en niet in Antlers:
     * een titel met </script> mag het scriptblok niet kunnen sluiten.
     */
    public function test_angle_brackets_are_hex_escaped_so_a_script_tag_cannot_close(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Article', '@id' => 'https://x.test/#a', 'headline' => 'Kapot </script> titel'])
            ->toJson();

        $this->assertStringNotContainsString('</script>', $json);
        // JSON_HEX_TAG zet elke '<' om naar het hex-escape; een rauwe '<' kan
        // dus nooit meer voorkomen. Het escape-teken bewijst dat er echt
        // geëscaped is en niet stilzwijgend gestript.
        $this->assertStringContainsString('\\u003C', $json);
        $this->assertSame('Kapot </script> titel', json_decode($json, true)['@graph'][0]['headline']);
    }

    public function test_accented_characters_stay_readable(): void
    {
        $json = (new SchemaGraph)
            ->add(['@type' => 'Article', '@id' => 'https://x.test/#a', 'headline' => 'één systeem'])
            ->toJson();

        $this->assertStringContainsString('één', $json);
    }
}
