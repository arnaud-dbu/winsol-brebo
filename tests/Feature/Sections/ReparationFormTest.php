<?php

namespace Tests\Feature\Sections;

use Illuminate\Support\Collection;
use Symfony\Component\Yaml\Yaml;

class ReparationFormTest extends SectionTestCase
{
    public function test_renders_every_blueprint_section_with_a_divider_between_them(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertSame($this->sections()->count(), substr_count($html, 'class="form-section"'));
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
     * `width: 50` in het blueprint, niet uit dit template — vandaar dat de
     * verwachting uit datzelfde blueprint komt en niet uit een vast getal.
     */
    public function test_the_paired_fields_are_marked_half_width(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $halve = $this->sections()
            ->flatMap(fn ($sectie) => $sectie['fields'])
            ->filter(fn ($veld) => ($veld['field']['width'] ?? 100) === 50)
            ->count();

        $this->assertSame($halve, substr_count($html, 'form-field--half'));
    }

    /**
     * De tarieven staan vóór het versturen op het formulier en moeten
     * bevestigd worden, net als op winsoldilbeek.be. Zonder die bevestiging
     * begint elke discussie over de factuur bij nul.
     */
    public function test_it_states_the_rates_and_requires_agreement(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('form-notice', $html);
        $this->assertStringContainsString('verplaatsingsforfait van 90 euro', $html);
        $this->assertStringContainsString('65 euro', $html);

        foreach (['rates_agreed', 'warranty_terms'] as $handle) {
            $this->assertMatchesRegularExpression(
                '~<input[^>]*name="'.$handle.'"[^>]*required~s',
                $html,
                "Akkoordverklaring {$handle} hoort verplicht te zijn.",
            );
        }

        // De garantievraag en de btw-verklaring bepalen wat er aangerekend
        // wordt; ze horen dus mee in de melding te belanden.
        foreach (['warranty', 'vat_six'] as $handle) {
            $this->assertStringContainsString('name="'.$handle.'"', $html, "Veld {$handle} ontbreekt.");
        }
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

        $this->assertSame(2, substr_count($html, 'class="form-dropzone"'));
        $this->assertSame(2, substr_count($html, 'type="file"'));

        // De naam van het gekozen bestand: zonder dit element ziet de bezoeker
        // niet dat zijn upload is aangekomen, want de file-input ligt
        // onzichtbaar over de dropzone.
        $this->assertSame(2, substr_count($html, 'data-file-name'));
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
        $this->assertMatchesRegularExpression('~>\s*Herstelling melden\s*<~', $html);
    }

    /**
     * Zonder bevestiging weet de bezoeker na het versturen niet of zijn
     * melding is aangekomen — het formulier verdween gewoon van het scherm.
     */
    public function test_it_confirms_the_submission_and_marks_the_button_while_sending(): void
    {
        $html = $this->render('{{ partial:reparationForm }}');

        $this->assertStringContainsString('data-sending-label', $html);
        $this->assertStringContainsString('{{ if success }}', file_get_contents(
            resource_path('views/partials/reparationForm.antlers.html'),
        ));
    }

    private function sections(): Collection
    {
        return collect(
            Yaml::parseFile(
                resource_path('blueprints/forms/herstelling.yaml'),
            )['tabs']['main']['sections'],
        );
    }
}
