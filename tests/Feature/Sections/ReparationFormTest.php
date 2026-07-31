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

        foreach (['product', 'installed', 'problem', 'branch', 'photo', 'email', 'name', 'phone'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }
    }

    public function test_marks_exactly_the_four_paired_fields_as_half_width(): void
    {
        // product+installed en name+phone staan in het design naast elkaar.
        // De klasse volgt uit `width: 50` in het blueprint, niet uit dit template.
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertSame(4, substr_count($html, 'form-field--half'));
    }

    public function test_the_branch_options_come_from_the_locations_collection(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
        $this->assertStringContainsString('Kies een filiaal…', $html);
    }

    public function test_the_photo_field_is_a_file_input_inside_the_dropzone(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('form-dropzone', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('Sleep een foto hierheen of klik om te uploaden', $html);
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
