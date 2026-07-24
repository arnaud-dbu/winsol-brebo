<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

class GridCols extends Modifier
{
    /**
     * Determine grid column classes based on the number of items.
     *
     * Grid logic:
     * - 2 cards: 2 columns
     * - 3 cards: 3 columns
     * - 4 cards: 4 columns (responsive: 2 on lg, 4 on 2xl)
     * - 5-6 cards: 3 columns
     * - 7+ cards: 4 columns (responsive: 2 on lg, 4 on 2xl)
     *
     * @param  array  $value  The array/collection of items to count
     * @param  array  $params  Optional parameters (unused)
     * @param  array  $context  Template context (unused)
     * @return string Tailwind CSS grid column classes
     */
    public function index($value, $params, $context)
    {
        $count = is_countable($value) ? count($value) : 0;

        return match (true) {
            $count === 2 => 'lg:grid-cols-2',
            $count === 3 => 'lg:grid-cols-3',
            $count === 4 => 'lg:grid-cols-2 xl:grid-cols-4',
            $count === 5 || $count === 6 => 'lg:grid-cols-3',
            $count >= 7 => 'lg:grid-cols-3 2xl:grid-cols-4',
            default => 'lg:grid-cols-2', // Fallback for 0-1 items
        };
    }
}
