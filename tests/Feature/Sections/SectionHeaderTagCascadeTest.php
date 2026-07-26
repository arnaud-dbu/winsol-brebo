<?php

namespace Tests\Feature\Sections;

/**
 * Regression test for a sectionHeader.antlers.html defect: `{{ tag = tag ?? "h2" }}`
 * used to be an Antlers assignment tag at the partial's top level, and Antlers
 * assignments write into the shared render cascade rather than a partial-local
 * scope. That meant `{{ partial:sectionHeader tag="h1" }}` followed later in the
 * same render by `{{ partial:sectionHeader }}` (no explicit tag) rendered BOTH
 * as `<h1>` — the second call silently inherited "h1" from the first.
 *
 * This is the same bug class already fixed once on this branch in
 * card.antlers.html's `layout` (see CardLayoutCascadeTest) — it is invisible to
 * any test that renders only one `sectionHeader` call, and only shows up when
 * two calls with different `tag` values render together in a single request,
 * as they do on a real page (e.g. `cta`'s `h1`-ish usage followed by any
 * default section further down).
 */
class SectionHeaderTagCascadeTest extends SectionTestCase
{
    public function test_an_h1_tag_call_does_not_leak_into_a_later_default_call(): void
    {
        $html = $this->render(
            '{{ partial:sectionHeader tag="h1" }}{{ partial:sectionHeader }}',
            [
                'title' => 'Some Title',
            ]
        );

        $this->assertSame(1, substr_count($html, '<h1 class="max-w-3xl">'));
        $this->assertSame(1, substr_count($html, '<h2 class="max-w-3xl">'));
    }
}
