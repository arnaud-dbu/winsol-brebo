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

    /**
     * Gated (Jimmy, werkoverleg 21/24-08): de kaart linkt niet meer naar de
     * pdf maar naar het brochureformulier, met deze pdf voorgeselecteerd via
     * de querystring. Linkt dit ooit weer rechtstreeks naar de pdf, dan is
     * de download gratis en levert de kaart geen leads meer op.
     */
    public function test_a_brochure_card_with_a_pdf_links_to_the_gated_form(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', array_merge($this->brochureCard(), [
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf', 'path' => 'brochures/pergola-so.pdf'],
        ]));

        $this->assertStringContainsString('href="/brochures?brochure=brochures/pergola-so.pdf"', $html);
        $this->assertStringNotContainsString('/assets/brochures/pergola-so.pdf', $html);
        $this->assertStringNotContainsString('target="_blank"', $html);
        $this->assertStringContainsString('Brochure aanvragen', $html);
    }

    public function test_a_brochure_card_without_a_link_falls_back_to_a_neutral_label(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', [
            'title' => 'Vraag brochure aan',
            'type' => 'brochure',
            'link_style' => 'outline',
            'brochure' => ['url' => '/assets/brochures/pergola-so.pdf', 'path' => 'brochures/pergola-so.pdf'],
        ]);

        $this->assertStringContainsString('Ontvang de brochure', $html);
    }

    public function test_a_brochure_card_without_a_pdf_renders_nothing(): void
    {
        $html = $this->render('{{ partial:quicklinkCard }}', $this->brochureCard());

        $this->assertStringNotContainsString('quicklink-card', $html);
        $this->assertSame('', trim($html));
    }
}
