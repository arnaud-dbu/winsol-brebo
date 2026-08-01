<?php

namespace Tests\Feature\Sections;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

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

        foreach (['Ramen en deuren', 'VELUX dakramen', 'Terrasoverkapping', 'Somfy Smart Home'] as $title) {
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

    /**
     * "Inside" is de assertie, niet alleen "aanwezig": drie losse
     * assertStringContainsString-checks slagen nog als `{{ field }}` buiten
     * `.form-dropzone` terechtkomt, terwijl form.css:84 dan niet meer matcht
     * en de pagina een kaal, zichtbaar file-input krijgt — precies de
     * regressie die de testnaam belooft te dekken. Vandaar posities
     * vergelijken, zoals test_the_pills_stay_outside_the_generic_field_wrapper
     * dat ook al doet.
     */
    public function test_the_attachment_field_is_a_file_input_inside_the_dropzone(): void
    {
        $html = $this->render('{{ partial:offerteForm }}');

        $dropzoneOpen = strpos($html, 'class="form-dropzone"');
        $this->assertNotFalse($dropzoneOpen, 'De dropzone ontbreekt.');

        $dropzoneClose = strpos($html, '</div>', $dropzoneOpen);
        $fileInput = strpos($html, 'type="file"');

        $this->assertNotFalse($fileInput, 'Het file-input ontbreekt.');
        $this->assertGreaterThan($dropzoneOpen, $fileInput, 'Het file-input hoort ná de opening van de dropzone te staan.');
        $this->assertLessThan($dropzoneClose, $fileInput, 'Het file-input hoort vóór de sluiting van de dropzone te staan.');

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

    /**
     * Elke vendor-fieldtype-view zet `aria-describedby` zelf op het veld dat
     * ze rendert; voor de handgeschreven pillengroep moet dat met de hand.
     * Zonder foutstatus staat het attribuut er niet — dat is geen bug, `{{
     * if error }}` is dan simpelweg leeg — dus deze test zet zelf een fout op
     * `products` in de errorbag die Statamic's form-tag leest
     * (`form.{handle}`, zie Statamic\Forms\Tags::sessionHandle()).
     */
    public function test_the_products_error_is_wired_to_the_group_for_assistive_tech(): void
    {
        $errors = new ViewErrorBag;
        $errors = $errors->put('form.offerte', new MessageBag([
            'products' => 'Kies minstens één product.',
        ]));
        session(['errors' => $errors]);

        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="offerte-form-products-field-error"', $html);
        $this->assertStringContainsString('id="offerte-form-products-field-error"', $html);
    }

    /**
     * De bevestiging vervángt het formulier in dezelfde kaart. Rendert de
     * veldloop ook na een geslaagde verzending, dan staat de bezoeker voor
     * een leeg formulier zonder verzendknop mét een bevestiging erboven.
     *
     * De succesvlag komt uit de sessie: `Tags::success()` leest
     * `{sessionHandle}.success`, en `sessionHandle()` is hier `form.offerte`
     * — dezelfde sleutel als de errorbag hierboven.
     */
    public function test_the_confirmation_replaces_the_form(): void
    {
        session(['form.offerte.success' => 'Bedankt voor uw aanvraag.']);

        $html = $this->render('{{ partial:offerteForm }}');

        $this->assertStringContainsString('offerte-success', $html);
        $this->assertStringContainsString('Uw aanvraag is verstuurd', $html);

        $this->assertStringNotContainsString('name="products[]"', $html);
        $this->assertStringNotContainsString('class="form-field', $html);
        $this->assertStringNotContainsString('name="honeypot"', $html);
        $this->assertStringNotContainsString('type="submit"', $html);
    }
}
