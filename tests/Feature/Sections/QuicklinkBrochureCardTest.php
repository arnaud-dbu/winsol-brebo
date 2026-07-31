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
        $this->assertStringNotContainsString('download', $html);
    }

    public function test_a_brochure_card_with_a_pdf_becomes_a_direct_download(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', array_merge($this->brochureCard(), [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf'],
        ]));

        $this->assertStringContainsString('/assets/brochures/pergola-so.pdf', $html);
        $this->assertStringContainsString('download', $html);
        $this->assertStringContainsString('Brochure aanvragen', $html);
    }

    public function test_a_brochure_card_without_a_pdf_renders_nothing(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', $this->brochureCard());

        $this->assertStringNotContainsString('quicklink-card', $html);
        $this->assertSame('', trim($html));
    }
}
