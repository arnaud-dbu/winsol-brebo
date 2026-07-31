<?php

namespace Tests\Feature\Commands;

use App\Services\ContentValueScanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Term;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class ImageGapsTest extends TestCase
{
    use CreatesTemporaryContent;

    public function test_it_reports_an_entry_that_still_uses_a_placeholder(): void
    {
        $this->temporaryEntry('products', 'nog-geen-beeld', [
            'title' => 'Nog geen beeld',
            'image' => 'placeholder/terras.jpg',
        ]);

        // Eén expectsOutputToContain() per assertion, met beide waarden in
        // dezelfde substring: `nog-geen-beeld` en `placeholder/terras.jpg`
        // staan op dezelfde regel, en de mock achter `artisan()` kent een
        // doWrite-aanroep maar aan één verwachting toe. Twee losse
        // expectsOutputToContain()-calls voor twee substrings op één regel
        // laten de tweede daardoor altijd falen — zie de andere tests in dit
        // bestand en het rapport voor de volledige toedracht.
        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('nog-geen-beeld | image | placeholder/terras.jpg')
            ->assertExitCode(1);
    }

    /**
     * De exitcode gaat over de héle site, en die draagt zolang de content niet
     * opgeleverd is nog dummybeeld — vandaar dat een entry met een echte foto
     * getoetst wordt op zijn eigen regels in het rapport en niet op de exitcode.
     * De schone uitkomst zelf staat in
     * `test_it_exits_clean_when_no_content_points_at_a_placeholder()`.
     */
    public function test_it_does_not_report_an_entry_whose_image_is_real(): void
    {
        $this->temporaryEntry('products', 'beeld-in-orde', [
            'title' => 'Beeld in orde',
            'image' => 'pergolas/echte-foto.jpg',
        ]);

        $this->assertSame([], $this->gapsFor('beeld-in-orde'));
    }

    public function test_it_exits_clean_when_no_content_points_at_a_placeholder(): void
    {
        $this->app->bind(ContentValueScanner::class, fn (): ContentValueScanner => new class extends ContentValueScanner
        {
            public function values(): array
            {
                return [];
            }
        });

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('Geen beeldgaten')
            ->assertExitCode(0);
    }

    public function test_it_points_at_the_exact_section_of_a_nested_replicator_gap(): void
    {
        $this->temporaryEntry('products', 'twaalf-secties', [
            'title' => 'Twaalf secties',
            'page_builder' => [
                [
                    'id' => 'sec01',
                    'type' => 'cta',
                    'title' => 'Eerste sectie',
                    'image' => 'winsol/echte-foto.jpg',
                ],
                [
                    'id' => 'sec02',
                    'type' => 'text_image',
                    'title' => 'Tweede sectie',
                    'image' => 'placeholder/tweede-sectie.jpg',
                ],
            ],
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('page_builder[1:text_image].image | placeholder/tweede-sectie.jpg')
            ->assertExitCode(1);
    }

    public function test_it_unwraps_the_asset_id_prefix_a_bard_image_node_stores(): void
    {
        $this->temporaryEntry('products', 'bard-gat', [
            'title' => 'Bard gat',
            'page_builder' => [
                [
                    'id' => 'sec01',
                    'type' => 'text',
                    'title' => 'Tekstblok',
                    'text' => [
                        [
                            'type' => 'image',
                            'attrs' => [
                                'src' => 'asset::assets::placeholder/in-tekst.jpg',
                                'alt' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('placeholder/in-tekst.jpg')
            ->assertExitCode(1);
    }

    public function test_it_recognizes_a_placeholder_folder_regardless_of_case(): void
    {
        $this->temporaryEntry('products', 'verkeerde-hoofdletter', [
            'title' => 'Verkeerde hoofdletter',
            'image' => 'Placeholder/Terras.jpg',
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('Placeholder/Terras.jpg')
            ->assertExitCode(1);
    }

    public function test_it_does_not_flag_prose_that_merely_mentions_the_word_placeholder(): void
    {
        $this->temporaryEntry('products', 'geen-vals-alarm', [
            'title' => 'Bekijk het aanbod (nog zonder placeholder/finale foto)',
            'image' => 'pergolas/echte-foto.jpg',
        ]);

        $this->assertSame([], $this->gapsFor('geen-vals-alarm'));
    }

    public function test_it_reports_a_placeholder_left_in_a_global_set(): void
    {
        $variables = GlobalVariables::find('seo::nl');
        $original = $variables->data()->all();

        $this->beforeApplicationDestroyed(function () use ($variables, $original): void {
            $variables->data($original);
            $variables->save();
        });

        $variables->data(['meta_image' => 'placeholder/social-share.jpg']);
        $variables->save();

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('global:seo | nl | meta_image | placeholder/social-share.jpg')
            ->assertExitCode(1);
    }

    public function test_it_reports_a_placeholder_left_on_a_taxonomy_term(): void
    {
        $term = Term::make('image-gaps-test')
            ->taxonomy('range_categories')
            ->set('title', 'Image Gaps Test')
            ->set('thumbnail', 'placeholder/categorie.jpg');
        $term->save();

        $this->beforeApplicationDestroyed(function () use ($term): void {
            $term->delete();
        });

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('image-gaps-test | thumbnail | placeholder/categorie.jpg')
            ->assertExitCode(1);
    }

    public function test_it_reports_the_dummy_images_folder_the_real_content_uses(): void
    {
        $this->temporaryEntry('products', 'nog-dummybeeld', [
            'title' => 'Nog dummybeeld',
            'image' => 'dummy-images/test-img-1.jpg',
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('nog-dummybeeld | image | dummy-images/test-img-1.jpg')
            ->assertExitCode(1);
    }

    public function test_it_reports_a_loose_dummy_file_that_has_no_folder(): void
    {
        $this->temporaryEntry('articles', 'los-dummybestand', [
            'title' => 'Los dummybestand',
            'image' => 'test-1.jpg',
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('los-dummybestand | image | test-1.jpg')
            ->assertExitCode(1);
    }

    public function test_it_does_not_flag_a_folder_that_merely_starts_like_a_dummy_one(): void
    {
        $this->temporaryEntry('products', 'geen-vals-alarm-op-map', [
            'title' => 'Geen vals alarm op map',
            'image' => 'testimonials/klant.jpg',
            'brochure' => 'dummy.pdf',
        ]);

        $this->assertSame(
            [],
            $this->gapsFor('geen-vals-alarm-op-map'),
            'Een map die toevallig met dezelfde letters begint is geen beeldgat'
        );
    }

    /**
     * De poort meet een conventie, en die conventie moet er een zijn die de
     * echte content werkelijk gebruikt. Deze test kijkt daarom niet naar een
     * fixture maar naar `content/` zelf: elk pad dat op een dummy- of
     * placeholdermap wijst, moet in het rapport staan. Zonder deze test bleef
     * `dummy-images/` — 31 verwijzingen in 19 bestanden — onzichtbaar terwijl
     * het commando `Geen beeldgaten.` meldde.
     *
     * Is de content bij oplevering schoon, dan is de verzameling leeg en
     * slaagt de test vanzelf.
     */
    public function test_the_gate_knows_every_placeholder_convention_the_real_content_uses(): void
    {
        $suspicious = $this->suspiciousPathsInRealContent();

        Artisan::call('winsol:image-gaps');
        $output = Artisan::output();

        $this->assertSame(
            $suspicious->all(),
            $suspicious->filter(fn (string $path): bool => str_contains($output, $path))->values()->all(),
            'winsol:image-gaps kent niet elke placeholder-conventie die in content/ voorkomt'
        );
    }

    public function test_it_reports_an_asset_in_use_that_still_carries_a_watermark(): void
    {
        $this->fakeAssetDisk();

        $source = storage_path('framework/testing/gaps-source');
        File::ensureDirectoryExists($source);
        File::copy(base_path('tests/fixtures/images/watermarked.jpg'), $source.'/used.jpg');
        $this->artisan('winsol:import-images', ['source' => $source, 'folder' => 'testrange-gaps']);
        File::deleteDirectory($source);

        $this->temporaryEntry('products', 'gewatermerkt-product', [
            'title' => 'Gewatermerkt product',
            'image' => 'testrange-gaps/used.jpg',
        ]);

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('watermerk | testrange-gaps/used.jpg')
            ->assertExitCode(1);
    }

    /**
     * De regels die dit commando over één entry rapporteert.
     *
     * @return list<string>
     */
    private function gapsFor(string $slug): array
    {
        Artisan::call('winsol:image-gaps');

        return collect(explode("\n", Artisan::output()))
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => str_contains($line, "| {$slug} |"))
            ->values()
            ->all();
    }

    /**
     * Paden in de echte content waarvan de eerste padcomponent een dummy- of
     * placeholdermap is. Het patroon eist een scheidingsteken na de naam, zodat
     * een echte map als `testimonials/` niet meetelt.
     *
     * @return Collection<int, string>
     */
    private function suspiciousPathsInRealContent(): Collection
    {
        return collect(app(ContentValueScanner::class)->values())
            ->pluck('value')
            ->filter(fn (string $value): bool => (bool) preg_match('/\.(jpe?g|png|webp)$/i', $value))
            ->filter(fn (string $value): bool => (bool) preg_match('#^(placeholder|dummy|temp|test)(s?[-_/]|s?\.)#i', $value))
            ->unique()
            ->values();
    }
}
