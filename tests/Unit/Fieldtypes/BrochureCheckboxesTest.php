<?php

namespace Tests\Unit\Fieldtypes;

use Illuminate\Support\Facades\Validator;
use Mockery;
use Statamic\Facades\Asset;
use Statamic\Facades\Form;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Fields\Field;
use Tests\TestCase;

class BrochureCheckboxesTest extends TestCase
{
    /**
     * De volgorde is die van de items in de brochures-globalset: dat is de
     * redactionele volgorde die ook de pillen op de brochurepagina bepaalt.
     */
    public function test_the_options_are_the_brochures_from_the_global(): void
    {
        $field = new Field('brochures', ['type' => 'brochure_checkboxes']);

        $items = GlobalSet::findByHandle('brochure_library')
            ->in(Site::current()->handle())
            ->get('items');

        // De verwachting komt uit de globalset zelf en niet uit een vaste
        // lijst: die brak bij elke toegevoegde brochure, terwijl wat hier
        // telt is dat de opties uit de globalset komen, in dezelfde
        // redactionele volgorde, met het pad als sleutel en het label als
        // waarde. Dat is precies wat de pillen op de brochurepagina en de
        // allowlist op het formulier delen.
        $this->assertSame(
            array_column($items, 'label', 'file'),
            $field->fieldtype()->extraRenderableFieldData()['options'],
        );

        // En een steekproef op de inhoud, zodat een leeggelopen of
        // onvertaalde globalset alsnog opvalt.
        $this->assertGreaterThanOrEqual(10, count($items));
        $this->assertContains('Rolluiken', array_column($items, 'label'));
    }

    /**
     * Zelfde gat als bij RangeCheckboxes: de allowlist staat op
     * `brochures.*` en doet dus alleen iets zolang de waarde een array is.
     * Vandaar de échte validator over de échte regelset.
     */
    public function test_a_forged_brochure_value_is_rejected(): void
    {
        $rules = Form::find('brochure')->blueprint()->fields()->validator()->rules();

        $this->assertTrue(
            Validator::make(['brochures' => 'GRATIS VIAGRA'], $rules)->fails(),
            'Een scalaire waarde hoort te falen; anders is de allowlist op brochures.* dode letter.',
        );

        $this->assertTrue(
            Validator::make(['brochures' => ['brochures/winsol-brochure-rolluiken-nl.pdf', 'niet-bestaand.pdf']], $rules)->fails(),
            'Een pad buiten de globalset hoort te falen.',
        );

        $this->assertFalse(
            Validator::make(
                [
                    'brochures' => ['brochures/winsol-brochure-rolluiken-nl.pdf'],
                    'name' => 'Jan',
                    'email' => 'jan@voorbeeld.be',
                    'phone' => '+32 470 00 00 00',
                    'address' => 'Teststraat 1, 1700 Dilbeek',
                    'gdpr' => '1',
                ],
                $rules,
            )->fails(),
            'Een echte brochure met een volledige lead hoort door te komen.',
        );
    }

    /**
     * De bevestigingsmail bouwt zijn downloadlinks uit de augment: label om
     * te tonen, url om te linken. Valt de url weg, dan mailt de site een
     * lijstje titels zonder brochures.
     *
     * De asset is gemockt, niet via Storage::fake() aangemaakt: een gefakete
     * r2-schijf laat de lege fake-listing achter in de file_testing-cache
     * (die runs overleeft, zie config/cache.php) en daarna rendert elke
     * pagina met echte beelden een 500 op de ontbrekende afmetingen.
     */
    public function test_a_stored_path_augments_to_label_and_url(): void
    {
        $asset = Mockery::mock();
        $asset->shouldReceive('url')->andReturn('/r2/brochures/winsol-brochure-rolluiken-nl.pdf');

        Asset::shouldReceive('find')
            ->with('assets::brochures/winsol-brochure-rolluiken-nl.pdf')
            ->andReturn($asset);

        $field = new Field('brochures', ['type' => 'brochure_checkboxes']);

        $augmented = $field->fieldtype()->augment(['brochures/winsol-brochure-rolluiken-nl.pdf']);

        $this->assertSame('Rolluiken', $augmented[0]['label']);
        $this->assertSame('/r2/brochures/winsol-brochure-rolluiken-nl.pdf', $augmented[0]['url']);
    }
}
