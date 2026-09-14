<?php

namespace Tests\Feature\Content;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Statamic\Facades\Entry;
use Statamic\Facades\Form;
use Statamic\Facades\Site;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Een formulier post naar `/!/forms/<handle>`, een route zonder taalprefix,
 * dus tijdens het verwerken staat `Site::current()` op de standaardsite. De
 * keuzelijst die de bezoeker zag, is wél in zijn eigen taal opgebouwd.
 *
 * Sinds de ranges per taal een eigen slug dragen (bd44d80, 09-09-2026) liep
 * dat mis: `protection-solaire` werd afgetoetst tegen een lijst met
 * `zonwering`. Zeven van de acht productgroepen faalden op /fr en /en, en bij
 * het offerteformulier zónder zichtbare fout, want de regel staat daar op
 * `products.*` en het sjabloon toont fouten per veldnaam. Vijf dagen lang
 * verdwenen Franse en Engelse offerteaanvragen daardoor spoorloos.
 */
class FormLanguageValidationTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function rangeSlugs(string $site): array
    {
        return Entry::query()
            ->where('collection', 'ranges')
            ->where('site', $site)
            ->whereStatus('published')
            ->get()
            ->map->slug()
            ->all();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formulieren(): array
    {
        return [
            'herstelformulier' => ['herstelling', 'product'],
            'offerteformulier' => ['offerte', 'products'],
        ];
    }

    #[DataProvider('formulieren')]
    public function test_a_choice_from_any_language_survives_the_post(string $formulier, string $veld): void
    {
        // Zoals tijdens een echte POST: de route draagt geen taal, dus de
        // standaardsite geldt.
        Site::setCurrent(Site::default()->handle());

        $rules = Form::find($formulier)->blueprint()->fields()->validator()->rules();

        foreach (['nl', 'fr', 'en'] as $site) {
            $slugs = $this->rangeSlugs($site);

            $this->assertNotEmpty($slugs, "Geen ranges op de {$site}-site om te controleren.");

            foreach ($slugs as $slug) {
                $waarde = $veld === 'products' ? [$slug] : $slug;
                $errors = Validator::make([$veld => $waarde], $rules)->errors();

                // Ook op `<veld>.0`: daar landt de fout bij een arrayveld, en
                // precies die bleef onzichtbaar in het formulier.
                $this->assertFalse(
                    $errors->has($veld) || $errors->has($veld.'.0'),
                    "De keuze {$slug} uit de {$site}-site wordt geweigerd op {$formulier}.",
                );
            }
        }
    }

    /**
     * De allowlist mag breder zijn dan de keuzelijst, maar de keuzelijst zelf
     * niet: die hoort de taal van de bezoeker te tonen en niet de drie talen
     * door elkaar.
     */
    public function test_the_options_stay_in_the_language_of_the_visitor(): void
    {
        foreach (['nl' => 'zonwering', 'fr' => 'protection-solaire', 'en' => 'solar-shading'] as $site => $verwacht) {
            Site::setCurrent($site);

            $veld = new Field('products', ['type' => 'range_checkboxes']);
            $opties = $veld->fieldtype()->extraRenderableFieldData()['options'];

            $this->assertArrayHasKey($verwacht, $opties, "De {$site}-keuzelijst mist {$verwacht}.");
            $this->assertCount(
                count($this->rangeSlugs($site)),
                $opties,
                "De {$site}-keuzelijst toont meer dan de ranges van die taal.",
            );
        }
    }
}
