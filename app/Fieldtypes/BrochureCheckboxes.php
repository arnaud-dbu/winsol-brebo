<?php

namespace App\Fieldtypes;

use Statamic\Facades\Asset;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Fieldtypes\Checkboxes;

/**
 * Checkboxes waarvan de opties uit de brochures-globalset komen, zodat het
 * formulier, de allowlist en de bevestigingsmail dezelfde redactionele lijst
 * delen. Zelfde opzet als RangeCheckboxes — zie daar voor waarom één
 * `getOptions()` volstaat en waarom `rules()`/`extraRules()` samen de
 * allowlist dragen.
 *
 * De opgeslagen waarde is het pad van de pdf in de assets-container: uniek
 * per brochure, leesbaar in de CP-submissielijst, en precies wat de mail
 * nodig heeft om de downloadlink te bouwen.
 */
class BrochureCheckboxes extends Checkboxes
{
    protected function getOptions(): array
    {
        return $this->items()
            ->map(fn ($item) => ['value' => $item['file'], 'label' => $item['label']])
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return ['array'];
    }

    public function extraRules(): array
    {
        return [
            $this->field->handle().'.*' => 'in:'.$this->items()->pluck('file')->implode(','),
        ];
    }

    /**
     * De mailview heeft naast het label ook de downloadlink nodig; de parent
     * augmenteert alleen naar value+label.
     */
    public function augment($value)
    {
        return collect(parent::augment($value))
            ->map(fn ($item) => $item + ['url' => Asset::find('assets::'.$item['value'])?->url()])
            ->all();
    }

    private function items()
    {
        $set = GlobalSet::findByHandle('brochures');

        // De labels volgen de taal van de bezoeker; de pdf-paden zijn in elke
        // taal dezelfde. Zonder localisatie valt de site terug op de default.
        $variables = $set?->in(Site::current()->handle()) ?? $set?->inDefaultSite();

        return collect($variables?->get('items') ?? []);
    }
}
