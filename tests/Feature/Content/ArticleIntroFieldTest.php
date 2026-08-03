<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Collection;
use Tests\TestCase;

class ArticleIntroFieldTest extends TestCase
{
    public function test_article_blueprint_uses_text_and_not_intro(): void
    {
        $blueprint = Collection::findByHandle('articles')->entryBlueprint();

        $this->assertTrue(
            $blueprint->hasField('text'),
            'De article-blueprint moet het intro-veld onder handle `text` dragen, want alle content en elk header-partial leest `text`.'
        );

        $this->assertFalse(
            $blueprint->hasField('intro'),
            'Handle `intro` schrijft naar een sleutel die niets rendert.'
        );
    }
}
