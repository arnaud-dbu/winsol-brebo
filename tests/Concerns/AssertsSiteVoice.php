<?php

namespace Tests\Concerns;

/**
 * De twee taalregels van de site, op één plek omdat elke vaste pagina ze deelt:
 * de site tutoyeert, en gedachtestreepjes zijn eruit omdat ze meeverhuizen zodra
 * iemand tekst kopieert naar een mail of een document.
 */
trait AssertsSiteVoice
{
    protected function assertSpeaksSiteVoice(string $text, string $label): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/[—–]/u',
            $text,
            "{$label} bevat een gedachtestreepje."
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\b[Uu]w?\b/u',
            $text,
            "{$label} spreekt in de u-vorm."
        );
    }

    /**
     * Bard levert een boom van nodes; alleen de `text`-bladeren dragen inhoud.
     *
     * @param  array<int, mixed>  $nodes
     */
    protected function flattenBard(array $nodes): string
    {
        $text = '';

        array_walk_recursive($nodes, function ($value, $key) use (&$text) {
            if ($key === 'text') {
                $text .= ' '.$value;
            }
        });

        return trim($text);
    }
}
