<?php

namespace Tests\Feature\Content;

use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * De beelden van een productpagina.
 *
 * Aanleiding (Jimmy/Quinten, 10-09-2026): op /aanbod/ramen-en-deuren stond bij
 * PVC ramen een close-up van deurklinken, bij PVC deuren een rolluik en bij
 * PVC schuiframen een garagepoort. De oorzaak was dat die drie producten
 * gevuld waren met losse realisatiefoto's van eenzelfde C-70-werf — daar staat
 * alles op wat er die dag geplaatst is — terwijl de per product gefotografeerde
 * beelden van winsol.eu ongebruikt op R2 stonden.
 *
 * Of een foto een raam of een deur toont, kan een test niet zien. Wat ze wél
 * kan bewaken, is de vorm van die fout: dezelfde foto op twee plaatsen.
 */
class ProductImagesTest extends TestCase
{
    /**
     * Aluminium ramen valt onder Aluminium ramen & deuren; dat die twee
     * dezelfde werf tonen is een keuze, geen vergissing.
     */
    private const GEDEELD_TOEGESTAAN = [
        ['aluminium-ramen', 'aluminium-ramen-en-deuren'],
        ['rolluiken-met-klassieke-lamellen', 'voorzetrolluiken'],
    ];

    public function test_no_product_shows_the_same_photo_twice(): void
    {
        // De galerij van Aluminium schuiframen herhaalde de hero en het beeld
        // uit het tekstblok: vier plaatsen, twee foto's.
        foreach ($this->producten() as $slug => $beelden) {
            $dubbel = array_keys(array_filter(array_count_values($beelden), fn ($n) => $n > 1));

            $this->assertSame([], $dubbel, "{$slug} toont " . implode(', ', $dubbel) . ' meer dan één keer');
        }
    }

    public function test_two_products_do_not_share_a_photo(): void
    {
        $producten = $this->producten();
        $gedeeld = [];

        foreach ($producten as $slug => $beelden) {
            foreach (array_unique($beelden) as $beeld) {
                $gedeeld[$beeld][] = $slug;
            }
        }

        foreach ($gedeeld as $beeld => $slugs) {
            if (count($slugs) < 2 || $this->magGedeeldWorden($slugs)) {
                continue;
            }

            $this->fail("{$beeld} staat bij " . implode(' én ', $slugs) . '; dan toont minstens één van de twee het verkeerde product');
        }

        $this->addToAssertionCount(1);
    }

    public function test_the_three_pvc_products_each_show_their_own_product(): void
    {
        // De bestandsnamen van de winsol.eu-beelden dragen de productnaam, dus
        // voor deze drie is de herkomst wél te controleren. Ze zijn de reden
        // dat deze test bestaat.
        $verwacht = [
            'pvc-ramen' => 'realisatie-realisation-pvc-c-70-(17).jpg',
            'pvc-deuren' => 'realisatie-realisation-pvc-ramen-en-deuren-c-70-portes-et-fenetres-c-70-rolluiken-volets-22.jpg',
            'pvc-schuiframen' => 'pvc-schuiframen-qubic-slide-3.webp',
        ];

        foreach ($verwacht as $slug => $kaartbeeld) {
            $product = Entry::query()
                ->where('collection', 'products')
                ->where('site', 'nl')
                ->where('slug', $slug)
                ->first();

            $this->assertNotNull($product, "Product {$slug} ontbreekt");
            $this->assertSame('ramen-en-deuren/' . $kaartbeeld, $product->get('image'));
        }

        // Elk pvc schuifraam-beeld komt uit een schuifraam-reeks. De 2D-tekening
        // in diezelfde reeks (qubic-slide-1) hoort er niet bij: dat is een
        // profieldoorsnede, geen foto.
        $schuiframen = $this->producten()['pvc-schuiframen'];

        foreach ($schuiframen as $beeld) {
            $this->assertStringContainsString('pvc-schuiframen-', $beeld, "{$beeld} is geen schuifraam-beeld");
            $this->assertStringNotContainsString('qubic-slide-1.webp', $beeld, 'Dat is de profieltekening, geen foto');
        }
    }

    /**
     * Hero, het beeld uit een tekstblok en de galerij, per product.
     *
     * @return array<string, list<string>>
     */
    private function producten(): array
    {
        $producten = [];

        foreach (glob(base_path('content/collections/products/nl/*.md')) as $bestand) {
            $slug = basename($bestand, '.md');
            $inhoud = file_get_contents($bestand);

            preg_match_all('/^\s*(?:-\s+)?image:\s*(\S+)\s*$|^\s+- (\S+\.(?:webp|jpg|jpeg|png))\s*$/m', $inhoud, $treffers, PREG_SET_ORDER);

            $producten[$slug] = array_map(
                fn ($t) => trim($t[2] ?? $t[1], "'\" "),
                $treffers
            );
        }

        return $producten;
    }

    /**
     * @param  list<string>  $slugs
     */
    private function magGedeeldWorden(array $slugs): bool
    {
        sort($slugs);

        foreach (self::GEDEELD_TOEGESTAAN as $paar) {
            sort($paar);

            if ($slugs === $paar) {
                return true;
            }
        }

        return false;
    }
}
