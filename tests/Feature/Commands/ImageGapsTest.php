<?php

namespace Tests\Feature\Commands;

use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Term;
use Tests\Concerns\CreatesTemporaryContent;
use Tests\TestCase;

class ImageGapsTest extends TestCase
{
    use CreatesTemporaryContent;

    protected function tearDown(): void
    {
        $this->deleteTemporaryEntries();

        parent::tearDown();
    }

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

    public function test_it_exits_clean_when_nothing_points_at_a_placeholder(): void
    {
        $this->temporaryEntry('products', 'beeld-in-orde', [
            'title' => 'Beeld in orde',
            'image' => 'pergolas/echte-foto.jpg',
        ]);

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

        $this->artisan('winsol:image-gaps')
            ->expectsOutputToContain('Geen beeldgaten')
            ->assertExitCode(0);
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
}
