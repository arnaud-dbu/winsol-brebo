<?php

namespace Tests\Feature\Sections;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Een schermlezer leest `aria-required` voor als "verplicht". Het staat op
 * precies de velden die ook `required` dragen, zodat browser en schermlezer
 * hetzelfde zeggen. De productpillen en de brochurekeuze tellen niet mee: dat
 * is een groep aanvinkvakjes waarvan er minstens één aan moet, en zo'n groep
 * kan geen `aria-required` dragen.
 */
class RequiredFieldsTest extends SectionTestCase
{
    /**
     * Het aantal komt uit de blueprints: elk veld met `required` of
     * `accepted` dat één eigen invoerveld heeft.
     *
     * @return array<string, array{string, int}>
     */
    public static function formulieren(): array
    {
        return [
            'offerte' => ['offerteForm', 10],
            'brochure' => ['brochureForm', 8],
            'herstelling' => ['reparationForm', 19],
        ];
    }

    /**
     * @return list<string>
     */
    private function invoervelden(string $html): array
    {
        preg_match_all('/<(?:input|select|textarea)\b[^>]*>/s', $html, $treffers);

        return $treffers[0];
    }

    #[DataProvider('formulieren')]
    public function test_every_required_field_says_so_to_a_screen_reader(string $partial, int $verplicht): void
    {
        $velden = $this->invoervelden($this->render("{{ partial:{$partial} }}"));

        $metRequired = array_filter($velden, fn (string $veld): bool => (bool) preg_match('/\srequired[\s>]/', $veld));
        $metAria = array_filter($velden, fn (string $veld): bool => str_contains($veld, 'aria-required="true"'));

        $this->assertCount($verplicht, $metRequired);
        $this->assertSame(array_keys($metRequired), array_keys($metAria), 'aria-required staat niet op dezelfde velden als required.');
    }

    /**
     * Wie niets gemarkeerd ziet, moet weten dat dat "verplicht" betekent. De
     * zin staat vóór het eerste veld, zodat een schermlezer hem hoort vóór de
     * productpillen, die zelf geen `aria-required` kunnen dragen.
     */
    #[DataProvider('formulieren')]
    public function test_the_form_opens_with_the_rule_for_unmarked_fields(string $partial): void
    {
        $html = $this->render("{{ partial:{$partial} }}");

        $zin = strpos($html, 'Alle velden zijn verplicht, tenzij er optioneel bij staat.');

        $this->assertNotFalse($zin, 'De zin over verplichte velden ontbreekt.');
        $this->assertLessThan(strpos($html, 'class="form-section"'), $zin, 'De zin staat niet vóór het eerste veld.');
    }
}
