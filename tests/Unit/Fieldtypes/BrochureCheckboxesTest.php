<?php

namespace Tests\Unit\Fieldtypes;

use Illuminate\Support\Facades\Validator;
use Mockery;
use Statamic\Facades\Asset;
use Statamic\Facades\Form;
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

        $this->assertSame([
            'brochures/winsol_brochure_ramen-en-deuren-in-alu_nl.pdf' => 'Aluminium ramen en deuren',
            'brochures/winsol_brochure_ramen-en-deuren-in-pvc_nl.pdf' => 'PVC ramen en deuren',
            'brochures/winsol-brochure-iqon-nl.pdf' => 'IQON minimalistisch schuifraam',
            'brochures/winsol-brochure-rolluiken-nl.pdf' => 'Rolluiken',
            'brochures/winsol-brochure-garagepoorten-nl.pdf' => 'Garagepoorten',
            'brochures/winsol_brochure_verticale-zonwering_nl.pdf' => 'Screens en verticale zonwering',
            'brochures/winsol_brochure_luifels_nl.pdf' => 'Zonneschermen',
            'brochures/winsol_luifel_lina-lumisolar_nl.pdf' => 'Zonneschermen op zonne-energie',
            'brochures/winsol-brochure_so-classic-climate_2025_nl.pdf' => 'Pergola SO!',
            'brochures/winsol-brochure-pergola-zip-nl.pdf' => 'Pergola Z!P',
            'brochures/pergola-origin_2025_nl.pdf' => 'Pergola ORIG!N',
            'brochures/winsol_brochure_so-cocoon_nl.pdf' => 'Pergola SO! Cocoon en Combi',
            'brochures/winsol_brochure_so-crystal_nl.pdf' => 'Pergola SO! Crystal',
        ], $field->fieldtype()->extraRenderableFieldData()['options']);
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
