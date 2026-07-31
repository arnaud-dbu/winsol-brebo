<?php

namespace Tests\Feature\Sections;

class TextImageMediaTest extends SectionTestCase
{
    public function test_it_renders_a_video_when_the_media_switch_is_video(): void
    {
        $html = $this->render('{{ partial:sections/textImage }}', [
            'title' => 'Pergola SO!',
            'media' => 'video',
            'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<picture', $html);
    }

    public function test_it_renders_nothing_in_the_media_column_when_the_switch_is_none(): void
    {
        $html = $this->render('{{ partial:sections/textImage }}', [
            'title' => 'Pergola SO!',
            'media' => 'none',
            'image' => 'dummy-images/test-img-1.jpg',
        ]);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<picture', $html);
        $this->assertStringContainsString('Pergola SO!', $html);
    }
}
