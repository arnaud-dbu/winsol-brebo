<?php

require __DIR__.'/../vendor/autoload.php';

/*
 * Statamic's Stache bouwt de 'collections'- en 'asset-containers'-config lazy
 * op via `Facades\Statamic\Stache\Traverser` — een real-time facade, dus een
 * gedeeld, muteerbaar singleton binnen één proces. Zodra multisite aanstaat
 * roept de eerste koude entry- of asset-query `Collection::findByHandle()`
 * aan tijdens zijn eigen bestandsfilter; is die config-store op dat moment
 * nog koud, dan bouwt dat er middenin een geneste Traverser-cyclus op
 * hetzelfde singleton-object, die na afloop het `filter`-veld van de
 * *buitenste, nog lopende* cyclus overschrijft. De rest van die buitenste
 * bestandenlijst filtert daarna met het verkeerde filter en verdwijnt.
 *
 * Met `CACHE_STORE=file_testing` (zie phpunit.xml) overleeft zo'n corrupte
 * lijst de hele testrun — en zonder expliciete opruiming ook een volgende
 * run, met een groene suite op verouderde data als risico. Daarom hier, vóór
 * er één test draait, in een apart proces: cache wissen, dan de Stache
 * warmen. Zo gebeurt de onvermijdelijke koude opbouw eenmalig en
 * gecontroleerd, in plaats van willekeurig middenin een test.
 *
 * `file_testing` (config/cache.php) is een eigen store op een eigen map,
 * losstaand van de `file`-store die de draaiende app gebruikt: anders wist
 * elke testrun de runtime-cache van de app, en lekken Storage::fake()-assets
 * uit een testrun de echte Stache in.
 *
 * De hele correctheid van de suite hangt aan deze warmup — zonder warmte
 * geeft de suite tientallen misleidende failures die niets met de
 * uiteindelijke oorzaak te maken hebben. Faalt een van beide stappen, dan
 * stopt de run hier hard: doorlopen met een koude of halfvolle cache is
 * zinlozer dan helemaal niet draaien.
 */
$root = dirname(__DIR__);
$cacheDir = $root.'/storage/framework/cache/testing';

// Alleen de inhoud wissen, niet de map zelf: die draagt een getrackte
// .gitignore die de rest van zijn eigen inhoud negeert.
exec(
    'find '.escapeshellarg($cacheDir).' -mindepth 1 ! -name .gitignore -delete 2>&1',
    $clearOutput,
    $clearExitCode
);

if ($clearExitCode !== 0) {
    fwrite(STDERR, "Stache-cache wissen vóór de testrun is mislukt (exit {$clearExitCode}):\n".implode("\n", $clearOutput)."\n");
    exit(1);
}

exec(
    'cd '.escapeshellarg($root).' && CACHE_STORE=file_testing APP_ENV=testing php artisan statamic:stache:warm --no-interaction 2>&1',
    $warmOutput,
    $warmExitCode
);

if ($warmExitCode !== 0) {
    fwrite(STDERR, "Stache-warmup vóór de testrun is mislukt (exit {$warmExitCode}):\n".implode("\n", $warmOutput)."\n");
    exit(1);
}
