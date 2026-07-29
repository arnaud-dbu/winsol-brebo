<?php

namespace Tests\Feature\Sections;

use PHPUnit\Framework\Attributes\DataProvider;

class TextImageSectionTest extends SectionTestCase
{
    public function test_renders_header_and_features(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Pergola SO!',
            'text' => '<p>Met draaibare lamellen.</p>',
            'features' => [['label' => 'Bediening via app']],
        ]);

        $this->assertStringContainsString('data-section="text_image"', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
        $this->assertStringContainsString('Bediening via app', $html);
    }

    public function test_adds_background_modifier_when_toggled(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
        ]);

        $this->assertStringContainsString('text-image--background', $html);
    }

    /**
     * De lichte kaart en de foto moeten op elkaar aansluiten: even hoog, met
     * een kleine gutter ertussen (Figma). `section-x-gap` doet het omgekeerde
     * — kolommen elk op hun eigen hoogte gecentreerd, ruime gutter — en wordt
     * hier dus vervangen, niet aangevuld.
     */
    public function test_background_variant_matches_the_card_to_the_image_height(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
        ]);

        $this->assertStringContainsString('lg:items-stretch', $html);
        $this->assertStringNotContainsString('section-x-gap', $html);
        $this->assertStringNotContainsString('items-center', $html);

        // De tekst centreert binnen de uitgerekte kaart, en de kaart krijgt
        // dezelfde radius als de foto ernaast. Lookaheads i.p.v. een vaste
        // volgorde: prettier-plugin-tailwindcss sorteert klassen.
        $this->assertMatchesRegularExpression(
            '/class="(?=[^"]*\bbg-light\b)(?=[^"]*\brounded-sm\b)(?=[^"]*\bjustify-center\b)[^"]*"/',
            $html
        );

        // Ruimere zijkanten dan `card-padding` geeft (Figma: ~96px op een kaart
        // van ~714px). Die twee komen later in de gebouwde CSS dan de
        // `card-padding`-regels, dus ze winnen bij gelijke specificiteit.
        $this->assertMatchesRegularExpression(
            '/class="(?=[^"]*\bcard-padding\b)(?=[^"]*\blg:px-16\b)(?=[^"]*\b2xl:px-24\b)[^"]*"/',
            $html
        );
    }

    /**
     * Onder `lg` staan kaart en foto onder elkaar: een halve kolom is daar te
     * smal voor een kaart met kop, lopende tekst én ruime zijpadding. De foto
     * krijgt dan een klasse waarmee ze vanaf `lg` de rij vult, zodat de twee
     * ook bij lange tekst gelijk eindigen (text-image.css).
     */
    public function test_background_variant_only_pairs_the_columns_from_lg(): void
    {
        // `{{ img }}` gooit in debug-modus op een fixture-url die geen echt
        // asset is; zie ImageGallerySectionTest voor de volledige uitleg.
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
            'image' => 'kantoor.jpg',
        ]);

        $this->assertStringContainsString('lg:flex-row', $html);
        $this->assertStringNotContainsString('sm:flex-row', $html);
        $this->assertStringNotContainsString('sm:w-1/2', $html);
        $this->assertStringContainsString('text-image__media', $html);
    }

    /**
     * Zonder kaart blijft alles bij het oude: ruime gutter, kolommen vanaf
     * `sm` naast elkaar, foto op haar eigen ratio.
     */
    public function test_default_variant_keeps_the_shared_column_layout(): void
    {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/textImage" }}', [
            'title' => 'Pergola SO!',
            'image' => 'kantoor.jpg',
        ]);

        $this->assertStringContainsString('section-x-gap items-center', $html);
        $this->assertStringContainsString('section-col-wide', $html);
        $this->assertStringContainsString('section-col-narrow', $html);
        $this->assertStringNotContainsString('lg:items-stretch', $html);
        $this->assertStringNotContainsString('text-image__media', $html);
    }

    public function test_omits_background_modifier_by_default(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Pergola SO!']);

        $this->assertStringNotContainsString('text-image--background', $html);
    }

    public function test_renders_the_anchor_as_a_section_id(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" anchor="advies" }}', [
            'title' => 'Advies op maat',
        ]);

        $this->assertStringContainsString('id="advies"', $html);
    }

    public function test_omits_the_id_attribute_without_an_anchor(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Advies op maat']);

        $this->assertStringNotContainsString('id="', $html);
    }

    public function test_puts_the_text_column_last_by_default(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" }}', ['title' => 'Advies op maat']);

        $this->assertStringContainsString('order-last', $html);

        // Blijft achter de foto staan zodra de kolommen naast elkaar komen.
        $this->assertStringNotContainsString('order-none', $html);
    }

    public function test_text_first_moves_the_text_column_ahead_of_the_image(): void
    {
        $html = $this->render('{{ partial src="sections/textImage" text_first="true" }}', [
            'title' => 'Vakkundige installatie',
        ]);

        $this->assertStringContainsString('sm:order-none', $html);
    }

    public function test_background_still_wins_over_text_first(): void
    {
        // `background` zette de tekstkolom al vooraan, mét lichte kaart. Dat gedrag
        // mag niet veranderen nu `text_first` hetzelfde effect langs een andere weg
        // bereikt: de acht bestaande aanroepers geven `text_first` nooit mee.
        $html = $this->render('{{ partial src="sections/textImage" text_first="true" }}', [
            'title' => 'Drie lokale verkooppunten',
            'background' => true,
        ]);

        $this->assertStringContainsString('bg-light card-padding', $html);
        $this->assertStringContainsString('lg:order-none', $html);
    }

    #[DataProvider('variantenProvider')]
    public function test_the_image_always_sits_on_top_while_the_columns_are_stacked(
        string $params,
        array $data,
        string $image_column
    ): void {
        config(['app.debug' => false]);

        $html = $this->render('{{ partial src="sections/textImage"'.$params.' }}', $data + [
            'title' => 'Advies op maat',
            'image' => 'kantoor.jpg',
        ]);

        // De tekstkolom staat vóór de fotokolom in de DOM — dat is de
        // leesvolgorde — en `order-last` op die kolom draait het visueel om
        // zolang de rij een kolom is. Zonder die klasse landt de foto onder
        // de tekst.
        $text_column = strpos($html, 'order-last');

        $this->assertIsInt(
            $text_column,
            'De foto hoort bovenaan te staan zolang de kolommen gestapeld zijn'
        );

        $this->assertLessThan(
            strpos($html, $image_column),
            $text_column,
            '`order-last` hoort op de tekstkolom te staan, niet op de fotokolom'
        );
    }

    public static function variantenProvider(): array
    {
        return [
            'standaard' => ['', [], 'section-col-narrow'],
            'text_first' => [' text_first="true"', [], 'section-col-narrow'],
            'background' => ['', ['background' => true], 'text-image__media'],
            'background + text_first' => [' text_first="true"', ['background' => true], 'text-image__media'],
        ];
    }
}
