<?php

namespace Tests\Feature\Content;

use Tests\TestCase;

/**
 * De brochurekaart op een productpagina linkt naar `/brochures?brochure=<pad>`.
 * Het `brochure`-veld staat alleen op de Nederlandse producten, dus de Franse
 * en Engelse erven het Nederlandse pad. In de Franse bibliotheek bestaat dat
 * pad niet — die draagt `..._fr.pdf` — waardoor er niets aangevinkt stond en
 * elke Franstalige bezoeker vanaf een productpagina opnieuw moest zoeken wat
 * hij net had aangeklikt.
 */
class BrochurePreselectTest extends TestCase
{
    private const ALU_NL = 'brochures/winsol_brochure_ramen-en-deuren-in-alu_nl.pdf';

    private const ALU_FR = 'brochures/winsol_brochure_ramen-en-deuren-in-alu_fr.pdf';

    private function aangevinkt(string $url): int
    {
        $response = $this->get($url);

        $response->assertOk();

        return substr_count($response->content(), ' checked');
    }

    public function test_a_dutch_path_preselects_on_the_dutch_page(): void
    {
        $this->assertSame(1, $this->aangevinkt('/brochures?brochure='.self::ALU_NL));
    }

    public function test_a_dutch_path_also_preselects_on_the_french_page(): void
    {
        $this->assertSame(1, $this->aangevinkt('/fr/brochures?brochure='.self::ALU_NL));
    }

    public function test_a_dutch_path_also_preselects_on_the_english_page(): void
    {
        $this->assertSame(1, $this->aangevinkt('/en/brochures?brochure='.self::ALU_NL));
    }

    /**
     * Een bezoeker die al op de Franse site zat, geeft het Franse pad mee. Dat
     * hoorde altijd al te werken en mag niet sneuvelen op de omzetting.
     */
    public function test_a_french_path_keeps_working_on_the_french_page(): void
    {
        $this->assertSame(1, $this->aangevinkt('/fr/brochures?brochure='.self::ALU_FR));
    }

    /**
     * De omzetting mag niet raden. Een pad dat in geen enkele taal bestaat
     * hoort niets te selecteren in plaats van de eerste de beste brochure.
     */
    public function test_an_unknown_path_selects_nothing(): void
    {
        $this->assertSame(0, $this->aangevinkt('/fr/brochures?brochure=onzin.pdf'));
        $this->assertSame(0, $this->aangevinkt('/brochures'));
    }

    /**
     * Het pad dat de Franse productpagina meegeeft, is precies het Nederlandse
     * pad uit het geerfde veld. Verandert dat ooit, dan dekt deze test de
     * aanname waarop de omzetting rust.
     */
    public function test_a_french_product_page_links_with_the_inherited_dutch_path(): void
    {
        $response = $this->get('/fr/gamme/chassis-et-portes/chassis-coulissants-en-aluminium');

        $response->assertOk();
        $response->assertSee('/fr/brochures?brochure='.self::ALU_NL, false);
    }
}
