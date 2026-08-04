<?php

namespace App\Inspace;

use Statamic\Fields\Field;

class HtmlWhitelist
{
    /**
     * Bard-button => de tags die hij toestaat. `p` en `br` staan altijd toe:
     * die horen bij de basisdoc en hebben geen button.
     */
    private const TAGS = [
        'h1' => ['h1'],
        'h2' => ['h2'],
        'h3' => ['h3'],
        'h4' => ['h4'],
        'h5' => ['h5'],
        'h6' => ['h6'],
        'bold' => ['strong', 'b'],
        'italic' => ['em', 'i'],
        'underline' => ['u'],
        'strikethrough' => ['s'],
        'unorderedlist' => ['ul', 'li'],
        'orderedlist' => ['ol', 'li'],
        'anchor' => ['a'],
        'table' => ['table', 'thead', 'tbody', 'tr', 'th', 'td'],
        'image' => ['img'],
        'quote' => ['blockquote'],
        'code' => ['code'],
        'codeblock' => ['pre', 'code'],
        'horizontalrule' => ['hr'],
    ];

    private const ALWAYS = ['p', 'br'];

    /**
     * @return list<string>
     */
    public function of(Field $field): array
    {
        $tags = self::ALWAYS;

        foreach ($field->get('buttons', []) as $button) {
            $tags = array_merge($tags, self::TAGS[$button] ?? []);
        }

        return array_values(array_unique($tags));
    }
}
