<?php

namespace App\Fieldtypes;

use Statamic\Facades\Entry;
use Statamic\Fieldtypes\Checkboxes;

/**
 * Checkboxes waarvan de opties uit de `ranges`-collectie komen, zodat de
 * productlijst niet naast die collectie een tweede keer in YAML bestaat.
 *
 * Eén overschreven `getOptions()` volstaat voor drie dingen, omdat de trait
 * `Statamic\Fieldtypes\HasSelectOptions` alles daaruit afleidt en die
 * aanroepen via `$this->` lopen:
 *
 *   - `extraRenderableFieldData()` geeft de opties door aan de
 *     `{{ fields }}`-loop in Antlers;
 *   - `getLabel()` zet een opgeslagen slug om naar de titel, waardoor de
 *     CP-submissielijst en de notificatiemail "Rolluiken" tonen in plaats
 *     van `rolluiken`.
 *
 * De opgeslagen waarde is de slug en niet de entry-id: die blijft leesbaar
 * in de mail en in een CSV-export, en overleeft het opnieuw aanmaken van
 * een entry.
 *
 * De handle `range_checkboxes` volgt uit de klassenaam
 * (`Fieldtype::handle()`), en `app/Fieldtypes` wordt automatisch gescand
 * door Statamic's ExtensionServiceProvider. Er is dus geen registratie
 * nodig.
 */
class RangeCheckboxes extends Checkboxes
{
    protected function getOptions(): array
    {
        return $this->ranges()
            ->map(fn ($entry) => ['value' => $entry->slug(), 'label' => $entry->get('title')])
            ->values()
            ->all();
    }

    /**
     * `Statamic\Fields\Field::rules()` hangt wat `rules()` teruggeeft aan de
     * veld-handle zelf. Voor een array moet de regel op `products.*` staan,
     * en dat kan alleen via `extraRules()`.
     */
    public function extraRules(): array
    {
        return [
            $this->field->handle().'.*' => 'in:'.$this->ranges()->map->slug()->implode(','),
        ];
    }

    /**
     * `order` is op de ranges-blueprint beschreven als volgorde binnen de
     * categorie, maar loopt in de praktijk uniek van 1 tot 9 over alle negen
     * entries en werkt dus als globale volgorde.
     */
    private function ranges()
    {
        return Entry::query()
            ->where('collection', 'ranges')
            ->orderBy('order')
            ->get();
    }
}
