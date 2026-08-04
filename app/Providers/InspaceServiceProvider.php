<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Statamic\Facades\Collection;

class InspaceServiceProvider extends ServiceProvider
{
    /**
     * Met revisions aan maakt save() een working copy in plaats van te
     * publiceren. Nova zou dan denken dat het publiceerde terwijl er niets
     * live staat — stil falen op precies de plek waar het het duurst is.
     */
    public function boot(): void
    {
        foreach (array_keys(config('inspace.writable', [])) as $handle) {
            if (Collection::findByHandle($handle)?->revisionsEnabled()) {
                throw new RuntimeException(
                    "De Inspace-adapter kan niet schrijven op `{$handle}`: revisions staan aan. ".
                    'Zet `revisions: false` in de collectie, of haal hem uit config/inspace.php.'
                );
            }
        }
    }
}
