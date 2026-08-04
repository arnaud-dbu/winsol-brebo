<?php

namespace Tests\Feature\Inspace;

use App\Inspace\TermValues;
use Illuminate\Support\Facades\Route;
use Statamic\Facades\Collection;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function test_every_route_appears_in_the_openapi_spec(): void
    {
        $spec = Yaml::parseFile(base_path('docs/inspace/openapi.yaml'));

        $documented = [];

        foreach ($spec['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = strtoupper($method).' '.$path;
            }
        }

        sort($documented);

        $actual = collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/inspace/v1/'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn (string $m) => in_array($m, ['HEAD', 'OPTIONS'], true))
                ->map(fn (string $m) => $m.' '.str_replace('api/inspace/v1', '', $route->uri())))
            ->sort()
            ->values()
            ->all();

        $this->assertSame($actual, $documented, 'De OpenAPI-spec loopt uit de pas met de routes.');
    }

    public function test_the_theme_enum_matches_the_taxonomy(): void
    {
        $spec = Yaml::parseFile(base_path('docs/inspace/openapi.yaml'));

        $inSpec = $spec['components']['schemas']['ArticleWrite']['properties']['theme']['enum'];
        $live = (new TermValues)->of(
            Collection::findByHandle('articles')->entryBlueprint()->field('themes')
        );

        sort($inSpec);
        sort($live);

        $this->assertSame($live, $inSpec);
    }
}
