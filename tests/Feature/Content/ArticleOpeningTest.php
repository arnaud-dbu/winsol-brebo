<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

class ArticleOpeningTest extends TestCase
{
    /**
     * Artikels van InSpace die ze zelf moeten rechtzetten (#12). Corrigeren
     * we ze hier, dan ziet InSpace niet waar hun invoer fout loopt, en hun
     * volgende API-update zou het toch overschrijven.
     *
     * @var list<string>
     */
    private const WAITING_FOR_INSPACE = [
        'velux-afmetingen',
        'pvc-of-aluminium-ramen',
    ];

    public function test_no_article_repeats_its_header_intro_in_the_opening_of_the_body(): void
    {
        $repeated = [];

        foreach (Entry::query()->where('collection', 'articles')->get() as $article) {
            if (in_array($article->slug(), self::WAITING_FOR_INSPACE, true)) {
                continue;
            }

            $intro = $this->significantWords((string) $article->get('text'));
            $opening = $this->significantWords($this->openingOf($article->augmentedValue('redactor')->value()));

            if ($intro === []) {
                continue;
            }

            $share = count(array_intersect($intro, $opening)) / count($intro);

            if ($share >= 0.5) {
                $repeated[] = sprintf('%s/%s (%d%%)', $article->locale(), $article->slug(), round($share * 100));
            }
        }

        $this->assertSame([], $repeated, 'Deze artikels herhalen de header-intro in hun eerste bodyalinea');
    }

    /**
     * De bodytekst vóór de eerste tussentitel. Een lege eerste alinea telt
     * niet als opening, anders zou een artikel dat met `<p></p>` begint
     * nooit vergeleken worden.
     *
     * @param  array<int, mixed>  $sets
     */
    private function openingOf(array $sets): string
    {
        $html = '';

        foreach ($sets as $set) {
            if ($set['type'] === 'text') {
                $html .= $set['text'];
            }
        }

        foreach (preg_split('#<h[1-6][^>]*>.*?</h[1-6]>#s', $html) as $part) {
            if (trim(strip_tags($part)) !== '') {
                return strip_tags($part);
            }
        }

        return '';
    }

    /**
     * Herhaling in andere woorden ziet deze vergelijking niet; ze vangt
     * alleen een intro die vrijwel woordelijk terugkomt.
     *
     * @return list<string>
     */
    private function significantWords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(html_entity_decode($text)));

        return array_values(array_unique(array_filter($words, fn (string $word) => mb_strlen($word) >= 5)));
    }
}
