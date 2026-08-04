<?php

namespace Tests\Unit\Inspace;

use App\Inspace\BlockConverter;
use App\Inspace\MissingBlockIdException;
use App\Inspace\UnknownBlockException;
use Statamic\Facades\Collection;
use Tests\TestCase;

class BlockConverterTest extends TestCase
{
    private function converter(?callable $transform = null): BlockConverter
    {
        $field = Collection::findByHandle('articles')->entryBlueprint()->field('redactor');

        return new BlockConverter($field, $transform);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nodes(): array
    {
        return [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Kop']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Eerste.']]],
            ['type' => 'set', 'attrs' => ['id' => 'vid01', 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Tweede.']]],
        ];
    }

    public function test_sets_split_the_stream_into_blocks(): void
    {
        $blocks = $this->converter()->toBlocks($this->nodes());

        $this->assertCount(3, $blocks);
        $this->assertSame('text', $blocks[0]['type']);
        $this->assertStringContainsString('<h2>Kop</h2>', $blocks[0]['html']);
        $this->assertStringContainsString('<p>Eerste.</p>', $blocks[0]['html']);

        $this->assertSame(['type' => 'video', 'id' => 'vid01', 'opaque' => true], $blocks[1]);

        $this->assertSame('text', $blocks[2]['type']);
        $this->assertStringContainsString('<p>Tweede.</p>', $blocks[2]['html']);
    }

    public function test_an_unchanged_round_trip_leaves_the_storage_byte_identical(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();

        $back = $converter->toProsemirror($converter->toBlocks($nodes), $nodes);

        $this->assertSame(
            json_encode($nodes),
            json_encode($back),
            'Een ongewijzigde ronde mag geen textAlign toevoegen: hergebruik de opgeslagen nodes.'
        );
    }

    public function test_a_rewritten_block_is_parsed_and_the_rest_is_untouched(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();
        $blocks = $converter->toBlocks($nodes);

        $blocks[0]['html'] = '<h2>Nieuwe kop</h2><p>Herschreven.</p>';

        $back = $converter->toProsemirror($blocks, $nodes);

        $this->assertSame('Nieuwe kop', $back[0]['content'][0]['text']);
        $this->assertSame('left', $back[0]['attrs']['textAlign'], 'Een herschreven blok mag wel normaliseren.');
        $this->assertSame($nodes[2], $back[2], 'De set moet ongewijzigd terugkomen.');
        $this->assertSame($nodes[3], $back[3], 'Het niet-herschreven blok blijft byte-identiek.');
    }

    public function test_an_opaque_box_is_restored_from_storage_not_from_the_payload(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();

        $back = $converter->toProsemirror([
            ['type' => 'video', 'id' => 'vid01', 'opaque' => true],
        ], $nodes);

        $this->assertSame([$nodes[2]], $back);
        $this->assertSame('https://youtu.be/x', $back[0]['attrs']['values']['video']);
    }

    public function test_an_omitted_box_is_deleted_and_a_reordered_one_moves(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();

        $onlyText = $converter->toProsemirror([
            ['type' => 'text', 'html' => $converter->toBlocks($nodes)[2]['html']],
        ], $nodes);

        $this->assertSame([$nodes[3]], $onlyText, 'Een weggelaten doos verdwijnt.');

        $reordered = $converter->toProsemirror([
            ['type' => 'video', 'id' => 'vid01', 'opaque' => true],
            ['type' => 'text', 'html' => $converter->toBlocks($nodes)[2]['html']],
        ], $nodes);

        $this->assertSame($nodes[2], $reordered[0]);
        $this->assertSame($nodes[3], $reordered[1]);
    }

    public function test_an_unknown_box_id_throws(): void
    {
        $this->expectException(UnknownBlockException::class);

        $this->converter()->toProsemirror([
            ['type' => 'video', 'id' => 'verzonnen', 'opaque' => true],
        ], $this->nodes());
    }

    public function test_the_transform_hook_only_sees_changed_blocks(): void
    {
        $seen = [];
        $converter = $this->converter(function (string $html) use (&$seen): string {
            $seen[] = $html;

            return $html;
        });

        $nodes = $this->nodes();
        $blocks = $converter->toBlocks($nodes);
        $blocks[0]['html'] = '<p>Anders.</p>';

        $converter->toProsemirror($blocks, $nodes);

        $this->assertSame(['<p>Anders.</p>'], $seen);
    }

    public function test_ambiguous_runs_with_identical_html_survive_an_unchanged_round_trip(): void
    {
        $converter = $this->converter();

        // Een knooptype dat de renderer niet kent, wordt stilzwijgend
        // overgeslagen: beide prozalopen renderen naar exact "<p>Ja.</p>",
        // terwijl de eerste loop er in werkelijkheid ook mysteryNode bij
        // heeft. Dat maakt de twee runs voor de HTML-vergelijking identiek
        // terwijl hun opgeslagen nodes dat niet zijn — de acceptatieprobe
        // voor C1: een ongewijzigde ronde mag hier nooit inhoud verliezen.
        $nodes = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ja.']]],
            ['type' => 'mysteryNode', 'attrs' => ['foo' => 'bar']],
            ['type' => 'set', 'attrs' => ['id' => 'vid01', 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ja.']]],
        ];

        $blocks = $converter->toBlocks($nodes);

        $this->assertSame($blocks[0]['html'], $blocks[2]['html'], 'Voorwaarde van het scenario: beide runs renderen identiek.');

        $back = $converter->toProsemirror($blocks, $nodes);

        // De blokken komen in dezelfde volgorde binnen als waarin ze
        // gesplitst zijn: elke run claimt zo de eerstvolgende kandidaat
        // onder zijn HTML-sleutel en krijgt exact zijn eigen nodes terug,
        // inclusief mysteryNode — byte-identiek, geen normalisatie.
        $this->assertSame(
            json_encode($nodes),
            json_encode($back),
            'Een ongewijzigde ronde blijft byte-identiek, ook als twee runs toevallig dezelfde HTML opleveren.'
        );
    }

    public function test_reordering_a_box_relative_to_ambiguous_runs_never_loses_content(): void
    {
        $converter = $this->converter();

        // Twee prozalopen renderen weer identiek ("<p>Ja.</p>"), nu
        // gescheiden door twee dozen. Beide dozen naar het einde
        // verplaatsen is een echte structurele wijziging, geen
        // ongewijzigde ronde.
        $nodes = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ja.']]],
            ['type' => 'mysteryNode', 'attrs' => ['foo' => 'bar']],
            ['type' => 'set', 'attrs' => ['id' => 'vid01', 'values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ja.']]],
            ['type' => 'set', 'attrs' => ['id' => 'vid02', 'values' => ['type' => 'video', 'video' => 'https://youtu.be/y']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nee.']]],
        ];

        $blocks = $converter->toBlocks($nodes);

        // Client groepeert de twee dubbelzinnige tekstblokken vóór beide
        // dozen in plaats van dat elke doos zijn eigen run scheidt.
        $reordered = [$blocks[0], $blocks[2], $blocks[1], $blocks[3], $blocks[4]];

        $back = $converter->toProsemirror($reordered, $nodes);

        // Elk binnenkomend blok claimt de eerstvolgende kandidaat onder
        // zijn sleutel: het eerste dubbelzinnige blok krijgt run 1 (met
        // mysteryNode), het tweede krijgt run 2. Geen enkele opgeslagen
        // node gaat verloren — alleen de positie van run 2 ten opzichte
        // van de eerste doos verschuift mee met de nieuwe structuur.
        $this->assertSame($nodes[0], $back[0], 'Paragraaf van run 1.');
        $this->assertSame($nodes[1], $back[1], 'mysteryNode van run 1 blijft behouden.');
        $this->assertSame($nodes[3], $back[2], 'Het tweede dubbelzinnige blok claimt run 2, niet run 1 nogmaals.');
        $this->assertSame($nodes[2], $back[3], 'De eerste doos blijft zichzelf, ongeacht zijn nieuwe positie.');
        $this->assertSame($nodes[4], $back[4]);
        $this->assertSame($nodes[5], $back[5]);

        $this->assertEqualsCanonicalizing(
            array_map('json_encode', $nodes),
            array_map('json_encode', $back),
            'Herordening verplaatst content, maar verliest er nooit een node van.'
        );
    }

    public function test_an_emptied_text_block_produces_no_nodes_instead_of_crashing(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();
        $blocks = $converter->toBlocks($nodes);
        $blocks[0]['html'] = '';

        $back = $converter->toProsemirror($blocks, $nodes);

        $this->assertSame($nodes[2], $back[0], 'De set blijft staan.');
        $this->assertSame($nodes[3], $back[1], 'Het niet-aangeraakte blok blijft byte-identiek.');
        $this->assertCount(2, $back, 'Het leeggemaakte blok levert geen enkele node op.');
    }

    public function test_a_set_without_a_stored_id_is_a_storage_defect_not_a_client_error(): void
    {
        $converter = $this->converter();
        $nodes = [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Tekst.']]],
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'video', 'video' => 'https://youtu.be/x']]],
        ];

        $this->expectException(MissingBlockIdException::class);

        $converter->toBlocks($nodes);
    }

    public function test_a_non_scalar_block_id_throws_instead_of_crashing(): void
    {
        $this->expectException(UnknownBlockException::class);

        $this->converter()->toProsemirror([
            ['type' => 'video', 'id' => ['x'], 'opaque' => true],
        ], $this->nodes());
    }

    public function test_reusing_the_same_box_id_twice_throws(): void
    {
        $this->expectException(UnknownBlockException::class);

        $this->converter()->toProsemirror([
            ['type' => 'video', 'id' => 'vid01', 'opaque' => true],
            ['type' => 'video', 'id' => 'vid01', 'opaque' => true],
        ], $this->nodes());
    }

    public function test_an_unrecognised_block_type_names_the_type_not_the_id(): void
    {
        try {
            $this->converter()->toProsemirror([
                ['type' => 'bogus', 'id' => 'vid01', 'opaque' => true],
            ], $this->nodes());

            $this->fail('Expected an UnknownBlockException.');
        } catch (UnknownBlockException $e) {
            $this->assertStringContainsString('bloktype', $e->getMessage());
            $this->assertStringContainsString('bogus', $e->getMessage());
        }
    }

    public function test_a_set_marker_in_client_html_is_dropped_not_rendered_broken(): void
    {
        $converter = $this->converter();
        $nodes = $this->nodes();

        $back = $converter->toProsemirror([
            ['type' => 'text', 'html' => '<p>Hoi</p><set>index-0</set>'],
        ], $nodes);

        foreach ($back as $node) {
            $this->assertNotSame('set', $node['type'] ?? null, 'Een set-marker uit client-HTML mag nooit als node terugkomen.');
        }
    }
}
