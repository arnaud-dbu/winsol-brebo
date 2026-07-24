<?php

namespace App\Listeners;

use Statamic\Events\BlueprintSaved;
use Statamic\Events\CollectionSaved;
use Statamic\Fields\Blueprint;

class AddDefaultBlueprintTabs
{
    public function handle(BlueprintSaved|CollectionSaved $event): void
    {
        if ($event instanceof CollectionSaved) {
            foreach ($event->collection->entryBlueprints() as $blueprint) {
                $this->injectTabs($blueprint);
            }
            return;
        }

        $blueprint = $event->blueprint;

        if (! str_starts_with($blueprint->namespace(), 'collections.')) {
            return;
        }

        $this->injectTabs($blueprint);
    }

    private function injectTabs(Blueprint $blueprint): void
    {
        $contents = $blueprint->contents();
        $tabs = $contents['tabs'] ?? [];
        $dirty = false;

        if (! isset($tabs['preview'])) {
            $contents['tabs']['preview'] = [
                'display' => 'Preview',
                'sections' => [
                    ['fields' => [['import' => 'preview']]],
                ],
            ];
            $dirty = true;
        }

        if (! isset($tabs['seo'])) {
            $contents['tabs']['seo'] = [
                'display' => 'SEO',
                'sections' => [
                    ['fields' => [['import' => 'seo']]],
                ],
            ];
            $dirty = true;
        }

        if ($dirty) {
            $blueprint->setContents($contents)->save();
        }
    }
}
