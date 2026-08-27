<?php

namespace Tests\Feature\Sections;

class ReparationFormTest extends SectionTestCase
{
    public function test_renders_both_blueprint_sections_with_a_divider_between_them(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertSame(2, substr_count($html, 'class="form-section"'));
    }

    public function test_renders_every_field_from_the_blueprint(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        foreach (['product', 'is_winsol', 'installed', 'facade', 'floor', 'dimensions', 'problem', 'branch', 'photo', 'invoice', 'email', 'name', 'phone', 'address'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }
    }

    /**
     * De halve velden staan per twee naast elkaar: product+is_winsol,
     * installed+facade, floor+... en name+phone. De klasse volgt uit
     * `width: 50` in het blueprint, niet uit dit template.
     */
    public function test_the_paired_fields_are_marked_half_width(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertSame(7, substr_count($html, 'form-field--half'));
    }

    /**
     * De vragen waarmee de planning weet hoeveel mensen en welk materiaal er
     * moeten komen — het punt waar Jimmy geld op verliest als ze ontbreken
     * (werkoverleg 21/24-08, formulier op winsoldilbeek.be).
     */
    public function test_it_asks_what_the_planning_needs_to_know(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        foreach (['facade', 'floor', 'dimensions', 'is_winsol'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }

        // De gevelkeuze is een select met drie vaste opties, geen vrij tekstveld.
        $this->assertStringContainsString('Voorgevel', $html);
        $this->assertStringContainsString('Achtergevel', $html);
        $this->assertStringContainsString('Zijgevel', $html);
    }

    /**
     * De productkeuze komt uit de ranges-collectie en niet uit een vaste lijst
     * in het blueprint: die zou op /fr en /en in het Nederlands staan.
     */
    public function test_the_product_options_come_from_the_published_ranges(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('Rolluiken', $html);
        $this->assertStringContainsString('Terrasoverkapping', $html);
        $this->assertStringNotContainsString('Airco', $html);
    }

    public function test_the_branch_options_come_from_the_locations_collection(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
        $this->assertStringContainsString('Kies een filiaal…', $html);
    }

    public function test_the_photo_and_invoice_fields_are_file_inputs_inside_their_dropzones(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertSame(2, substr_count($html, 'form-dropzone'));
        $this->assertSame(2, substr_count($html, 'type="file"'));
        $this->assertStringContainsString('Sleep een foto hierheen of klik om te uploaden', $html);
        $this->assertStringContainsString('Sleep je factuur hierheen of klik om te uploaden', $html);
    }

    public function test_accepts_file_uploads(): void
    {
        // Statamic zet enctype alleen als het blueprint een assets-veld heeft.
        // Valt `photo` weg, dan verdwijnt dit attribuut stil en uploadt de
        // browser alleen de bestandsnaam.
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    public function test_every_label_points_at_its_control(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('for="herstelling-form-product-field"', $html);
        $this->assertStringContainsString('id="herstelling-form-product-field"', $html);
    }

    public function test_the_branch_label_points_at_the_handwritten_select(): void
    {
        // De generieke tak (hierboven, `product`) laat `{{ field }}` zijn eigen
        // id zetten. `branch` is handgeschreven omdat de opties uit de
        // locations-collectie komen, dus juist daar is een mismatch tussen
        // `<label for>` en `<select id>` het waarschijnlijkst.
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('for="herstelling-form-branch-field"', $html);
        $this->assertStringContainsString('id="herstelling-form-branch-field"', $html);
    }

    public function test_the_submit_button_uses_the_accent_style(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        // De accentknop heet sinds 95da753 `btn--primary`: die utility kreeg
        // toen `bg-accent text-black` en `btn--accent` verdween uit button.css.
        $this->assertStringContainsString('btn btn--primary', $html);
        $this->assertStringContainsString('>Herstelling melden<', $html);
    }
}
