<?php

namespace App\Tags;

use App\Schema\ArticleSchema;
use App\Schema\BreadcrumbSchema;
use App\Schema\LocationsSchema;
use App\Schema\OrganizationSchema;
use App\Schema\SchemaGraph;
use App\Schema\ServiceSchema;
use Illuminate\Support\Facades\Log;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Tags\Tags;

/**
 * Rendert één JSON-LD-graph in de <head>. Bepaalt alleen wélke bouwers bij
 * de huidige pagina horen; van schema.org zelf weet deze klasse niets.
 */
class Schema extends Tags
{
    protected static $handle = 'schema';

    private const SERVICE_COLLECTIONS = ['products', 'ranges'];

    /**
     * Deze laag is decoratief en staat in de <head> van élke pagina: een fout
     * hier mag alleen het JSON-LD-blok kosten, nooit de rest van de pagina.
     */
    public function index(): string
    {
        try {
            $graph = (new SchemaGraph)
                ->add(OrganizationSchema::node())
                ->addAll(LocationsSchema::nodes());

            $entry = $this->currentEntry();

            if ($entry !== null) {
                $graph->add(BreadcrumbSchema::node(
                    (string) $entry->uri(),
                    (string) $entry->get('title'),
                ));

                $collection = $entry->collection()?->handle();

                if (in_array($collection, self::SERVICE_COLLECTIONS, true)) {
                    $graph->add(ServiceSchema::node($entry));
                } elseif ($collection === 'articles') {
                    $graph->add(ArticleSchema::node($entry));
                }
            }

            if ($graph->isEmpty()) {
                return '';
            }

            return '<script type="application/ld+json">'.$graph->toJson().'</script>';
        } catch (\Throwable $e) {
            Log::warning('JSON-LD-graph kon niet gebouwd worden: '.$e->getMessage());

            return '';
        }
    }

    /**
     * De cascade draagt de id van de huidige entry. Op pagina's waar dat niet
     * zo is, valt hij terug op de URI, zodat de graph nooit stilvalt.
     */
    private function currentEntry(): ?EntryContract
    {
        $id = $this->context->get('id');

        if ($id && ($entry = Entry::find($id))) {
            return $entry;
        }

        return Entry::findByUri('/'.trim(request()->path(), '/'));
    }
}
