<?php

namespace Tests\Unit\Fieldtypes;

use Illuminate\Support\Facades\Validator;
use Statamic\Facades\Form;
use Statamic\Fields\Field;
use Tests\TestCase;

class RangeCheckboxesTest extends TestCase
{
    /**
     * De volgorde is die van het `order`-veld op de ranges-entries (1 t/m 9,
     * uniek over alle negen), niet de volgorde uit het Figma-ontwerp. Die
     * laatste is willekeurige vulling en zou de lijst hier opnieuw
     * hardcoderen.
     */
    public function test_the_options_are_the_ranges_in_their_own_order(): void
    {
        $field = new Field('products', ['type' => 'range_checkboxes']);

        // Zonder airco: gedepubliceerde ranges horen ook uit het formulier
        // (Quinten, 26-08) — de opties volgen de publicatiestatus.
        $this->assertSame([
            'ramen-en-deuren' => 'Ramen en deuren',
            'stalen-binnendeuren' => 'Stalen binnendeuren',
            'velux' => 'VELUX dakramen',
            'rolluiken' => 'Rolluiken',
            'zonwering' => 'Zonwering',
            'terrasoverkapping' => 'Terrasoverkapping',
            'garagepoorten' => 'Garagepoorten',
            'somfy-smart-home' => 'Somfy Smart Home',
        ], $field->fieldtype()->extraRenderableFieldData()['options']);
    }

    /**
     * De allowlist staat op `products.*`, dus hij doet alleen iets zolang
     * `products` ook echt een array ís: Laravel matcht een scalaire waarde
     * nooit tegen dat patroon en laat hem dan door met alleen `required`.
     *
     * Deze test draait daarom de échte validator over de échte regelset uit
     * het blueprint, en niet — zoals zijn voorganger — een assertie op de
     * aanwezigheid van een regelstring. Die slaagde ook toen de gaten er nog
     * in zat.
     */
    public function test_a_forged_product_value_is_rejected(): void
    {
        $rules = Form::find('offerte')->blueprint()->fields()->validator()->rules();

        $this->assertTrue(
            Validator::make(['products' => 'GRATIS VIAGRA'], $rules)->fails(),
            'Een scalaire waarde hoort te falen; anders is de allowlist op products.* dode letter.',
        );

        $this->assertTrue(
            Validator::make(['products' => ['rolluiken', 'niet-bestaand']], $rules)->fails(),
            'Een slug buiten de ranges-collectie hoort te falen.',
        );

        $this->assertTrue(
            Validator::make(['products' => ['airco']], $rules)->fails(),
            'Een gedepubliceerde range hoort geweigerd te worden.',
        );

        $this->assertFalse(
            Validator::make(
                [
                    'products' => ['rolluiken', 'zonwering'],
                    'location' => 'winsol-dilbeek',
                    'name' => 'Jan',
                    'phone' => '+32 470 00 00 00',
                    'email' => 'jan@voorbeeld.be',
                    'address' => 'Teststraat 1, 1700 Dilbeek',
                    'project' => 'Twee rolluiken vooraan.',
                ],
                $rules,
            )->fails(),
            'Twee echte slugs horen door te komen.',
        );
    }

    /**
     * De labels in de CP-submissielijst en in de notificatiemail komen van
     * `getLabel()`, dat op dezelfde opties leunt. Rendert dit ruwe slugs, dan
     * is de koppeling met de collectie stuk.
     */
    public function test_a_stored_slug_augments_to_its_title(): void
    {
        $field = new Field('products', ['type' => 'range_checkboxes']);

        $augmented = $field->fieldtype()->augment(['rolluiken']);

        $this->assertSame('Rolluiken', $augmented[0]['label']);
    }
}
