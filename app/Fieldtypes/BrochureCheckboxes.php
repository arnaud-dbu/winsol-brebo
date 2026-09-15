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

    /**
     * De allowlist loopt over alle talen, niet over de huidige.
     *
     * Het formulier post naar `/!/forms/brochure`, een route zonder taalprefix,
     * dus `Site::current()` is daar de standaardtaal. Sinds de Franse brochures
     * hun eigen pdf's kregen (`..._fr.pdf`) stond de keuze van een Franse
     * bezoeker niet in die Nederlandse lijst. De regel faalt dan op
     * `brochures.0` en niet op `brochures`, waardoor het formulier zonder
     * zichtbare fout terugkeerde en de inzending stil verdween — geen
     * opgeslagen submissie, geen mail, geen logregel.
     */
    public function extraRules(): array
    {
        return [
            $this->field->handle().'.*' => 'in:'.$this->bestandenInAlleTalen()->implode(','),
        ];
    }

    private function bestandenInAlleTalen()
    {
        $set = GlobalSet::findByHandle('brochure_library');

        return collect(Site::all())
            ->map(fn ($site) => $set?->in($site->handle()))
            ->filter()
            ->flatMap(fn ($variables) => collect($variables->get('items') ?? [])->pluck('file'))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * De mailview heeft naast het label ook de downloadlink nodig; de parent
     * augmenteert alleen naar value+label.
     *
     * Een keuze kan meer dan één pdf opleveren. Een item in de bibliotheek
     * mag `extra_files` dragen, en die gaan mee in de bevestigingsmail zonder
     * dat de bezoeker ze apart moet aanvinken: wie een pergolabrochure
     * vraagt, krijgt de zonneschermen er zo vanzelf bij (Jimmy, 10-09-2026).
     */
    public function augment($value)
    {
        $bibliotheek = $this->items()->keyBy('file');

        return collect(parent::augment($value))
            ->flatMap(fn ($item) => collect([$item['value']])
                ->merge($bibliotheek[$item['value']]['extra_files'] ?? []))
            // Ontdubbelen op pad. Twee regels in de lijst mogen naar dezelfde
            // pdf wijzen — Rolluiken en Verticale zonwering doen dat sinds
            // Jimmy's correctie — en een extra kan samenvallen met iets dat de
            // bezoeker zelf al aanvinkte. Zonder dit staat dezelfde brochure
            // twee keer in de mail.
            ->unique()
            ->map(fn ($pad) => [
                'value' => $pad,
                'label' => $bibliotheek[$pad]['label'] ?? $pad,
                'url' => Asset::find('assets::'.$pad)?->url(),
            ])
            ->values()
            ->all();
    }

    private function items()
    {
        // `brochure_library` en niet `brochures`: Statamic zet elke globalset
        // onder zijn handle op topniveau in de data van een formuliermail, en
        // een set die `brochures` heet overschrijft daar het gelijknamige
        // formulierveld — de gekozen brochures vielen dan uit de mail weg.
        $set = GlobalSet::findByHandle('brochure_library');

        // De labels volgen de taal van de bezoeker; de pdf-paden zijn in elke
        // taal dezelfde. Zonder localisatie valt de site terug op de default.
        $variables = $set?->in(Site::current()->handle()) ?? $set?->inDefaultSite();

        return collect($variables?->get('items') ?? []);
    }
}
