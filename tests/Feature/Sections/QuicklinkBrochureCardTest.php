<?php

namespace Tests\Feature\Sections;

class QuicklinkBrochureCardTest extends SectionTestCase
{
    private function brochureCard(): array
    {
        return [
            'title' => 'Vraag brochure aan',
            'text' => 'Alles over dit product in een pdf.',
            'type' => 'brochure',
            'link_style' => 'outline',
            'link' => [[
                'type' => 'url',
                'url' => 'example.com',
                'label' => 'Brochure aanvragen',
            ]],
        ];
    }

    public function test_a_default_card_is_untouched_by_the_brochure_logic(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', [
            'title' => 'Bezoek een showroom',
            'type' => 'default',
            'link_style' => 'outline',
            'link' => [[
                'type' => 'url',
                'url' => 'example.com',
                'label' => 'Plan een bezoek',
            ]],
        ]);

        $this->assertStringContainsString('Bezoek een showroom', $html);
        $this->assertStringContainsString('Plan een bezoek', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
    }

    public function test_a_brochure_card_with_a_pdf_opens_it_in_a_new_tab(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', array_merge($this->brochureCard(), [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]));

        $this->assertStringContainsString('/assets/brochures/pergola-so.pdf', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
        $this->assertStringContainsString('Brochure aanvragen', $html);

        // R2 draait op een andere origin dan de site: `download` wordt daar
        // door de browser genegeerd en belooft dus gedrag dat niet gebeurt.
        $this->assertStringNotContainsString('download', $html);
    }

    public function test_a_brochure_card_without_a_link_falls_back_to_a_neutral_label(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', [
            'title' => 'Vraag brochure aan',
            'type' => 'brochure',
            'link_style' => 'outline',
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]);

        $this->assertStringContainsString('Bekijk de brochure', $html);
    }

    public function test_a_brochure_card_without_a_pdf_renders_nothing(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', $this->brochureCard());

        $this->assertStringNotContainsString('quicklink-card', $html);
        $this->assertSame('', trim($html));
    }
}
