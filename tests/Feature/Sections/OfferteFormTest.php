<?php

namespace Tests\Feature\Sections;

class OfferteFormTest extends SectionTestCase
{
    public function test_renders_every_field_from_the_blueprint(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        foreach (['location', 'name', 'phone', 'email', 'postal_code', 'project', 'attachment'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }

        // products is een array, dus de naam draagt haakjes.
        $this->assertStringContainsString('name="products[]"', $html);
    }

    /**
     * Negen pillen met de titels uit de ranges-collectie. Dit is de assertie
     * die stukgaat als de koppeling met die collectie wegvalt.
     */
    public function test_the_product_pills_come_from_the_ranges_collection(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertSame(9, substr_count($html, 'offerte-pill"'));

        foreach (['Ramen en deuren', 'VELUX dakramen', 'Terrasoverkappingen &amp; pergola&#039;s', 'Somfy Smart Home'] as $title) {
            $this->assertStringContainsString($title, $html);
        }
    }

    public function test_the_branch_options_come_from_the_locations_collection(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('Winsol Dilbeek', $html);
        $this->assertStringContainsString('Winsol Sint-Pieters-Leeuw', $html);
        $this->assertStringContainsString('Winsol Aartselaar', $html);
        $this->assertStringContainsString('Kies een filiaal…', $html);
    }

    /**
     * name+phone en email+postal_code staan in het ontwerp naast elkaar. De
     * klasse volgt uit `width: 50` in het blueprint, niet uit het template.
     */
    public function test_marks_exactly_the_four_paired_fields_as_half_width(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertSame(4, substr_count($html, 'form-field--half'));
    }

    public function test_the_attachment_field_is_a_file_input_inside_the_dropzone(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('form-dropzone', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('Sleep een foto hierheen of klik om te uploaden', $html);
    }

    /**
     * Statamic zet enctype alleen als het blueprint een assets-veld heeft.
     * Valt `attachment` weg, dan verdwijnt dit attribuut stil en uploadt de
     * browser alleen de bestandsnaam.
     */
    public function test_accepts_file_uploads(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
    }

    public function test_carries_a_honeypot(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('name="honeypot"', $html);
    }

    /**
     * De pillen staan bewust buiten `.form-field`: form.css stijlt daarbinnen
     * elke input met een volle-breedte grijze vulling, en dat zou de
     * checkboxes van de pillen raken.
     *
     * `products` is het eerste veld in het blueprint, dus alle pillen horen
     * vóór de eerste `.form-field`-wrapper te staan. Dat is de assertie —
     * niet een vast aantal tekens vanaf `offerte-products`, want de pillen
     * groeien mee met de collectie.
     */
    public function test_the_pills_stay_outside_the_generic_field_wrapper(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $firstFieldWrapper = strpos($html, 'class="form-field');

        $this->assertNotFalse($firstFieldWrapper, 'Geen enkel veld gebruikt de generieke wrapper.');
        $this->assertLessThan($firstFieldWrapper, strpos($html, 'offerte-products'));
        $this->assertLessThan($firstFieldWrapper, strrpos($html, 'offerte-pill"'));
    }
}
