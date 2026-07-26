<?php

namespace Tests\Feature\Sections;

/**
 * Regression test for a card.antlers.html defect: `{{ layout = layout ?? "vertical" }}`
 * used to be an Antlers assignment tag, and Antlers assignments write into the
 * shared render cascade rather than a partial-local scope. That meant a
 * `cards` section (layout="horizontal") rendered before a `products` section
 * (no explicit layout) on the same page would leak "horizontal" into the
 * later `partial:card` calls, silently corrupting them.
 *
 * This is invisible to any test that renders only one section — it only
 * shows up when two sections that use `card` differently are rendered
 * together in a single request, as they are on a real page.
 */
class CardLayoutCascadeTest extends SectionTestCase
{
    public function test_a_horizontal_cards_section_does_not_leak_into_a_later_products_section(): void
    {
        $html = $this->render(
            '{{ partial src="sections/cards" }}{{ partial src="sections/products" }}',
            [
                'title' => 'Alle mogelijkheden op een rij',
                'cards' => [
                    ['title' => 'Sfeervolle ledverlichting', 'text' => '<p>Dimbare spots.</p>'],
                ],
                'products' => [
                    ['title' => 'Pergola SO!', 'text' => '<p>Draaibare lamellen.</p>'],
                ],
            ]
        );

        $this->assertSame(1, substr_count($html, 'card--vertical'));
        $this->assertSame(1, substr_count($html, 'card--horizontal'));
    }
}
