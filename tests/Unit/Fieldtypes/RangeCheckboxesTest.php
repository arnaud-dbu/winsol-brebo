<?php

namespace Tests\Unit\Fieldtypes;

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

        $this->assertSame([
            'ramen-en-deuren' => 'Ramen en deuren',
            'stalen-binnendeuren' => 'Stalen binnendeuren',
            'velux' => 'VELUX dakramen',
            'airco' => 'Airco',
            'rolluiken' => 'Rolluiken',
            'zonwering' => 'Zonwering',
            'pergolas' => "Terrasoverkappingen & pergola's",
            'garagepoorten' => 'Garagepoorten',
            'somfy-smart-home' => 'Somfy Smart Home',
        ], $field->fieldtype()->extraRenderableFieldData()['options']);
    }

    /**
     * De regel hoort op `products.*` en niet op `products`: het is een array.
     * Zonder deze regel kan een vervalste POST willekeurige tekst in de
     * notificatiemail zetten.
     */
    public function test_a_forged_product_value_is_rejected(): void
    {
        $field = new Field('products', ['type' => 'range_checkboxes']);

        $this->assertContains(
            'in:ramen-en-deuren,stalen-binnendeuren,velux,airco,rolluiken,zonwering,pergolas,garagepoorten,somfy-smart-home',
            $field->rules()['products.*'],
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
