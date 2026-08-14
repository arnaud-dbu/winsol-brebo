<?php

namespace App\Schema;

/**
 * Verzamelt schema.org-nodes en encodeert ze als één @graph.
 *
 * Kent bewust niets van Winsol of van specifieke types: alleen nodes, @id's
 * en het snoeien van lege waarden.
 */
class SchemaGraph
{
    private const FLAGS = JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /** @var array<array-key, array<string, mixed>> */
    private array $nodes = [];

    /**
     * @param  array<string, mixed>|null  $node
     */
    public function add(?array $node): static
    {
        if ($node === null) {
            return $this;
        }

        $node = self::prune($node);

        if ($node === []) {
            return $this;
        }

        $this->nodes[$node['@id'] ?? count($this->nodes)] = $node;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>|null>  $nodes
     */
    public function addAll(array $nodes): static
    {
        foreach ($nodes as $node) {
            $this->add($node);
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function toJson(): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@graph' => array_values($this->nodes),
        ], self::FLAGS);
    }

    /**
     * Verwijdert null, lege strings en lege arrays, recursief. Half ingevulde
     * markup is voor Google slechter dan afwezige markup.
     *
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private static function prune(array $node): array
    {
        $isList = array_is_list($node);
        $pruned = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $value = self::prune($value);

                // Een genest object dat na het snoeien alleen nog '@type' overhoudt
                // (bv. geo zonder coördinaten) draagt geen informatie en telt als leeg.
                if (! array_is_list($value) && array_keys($value) === ['@type']) {
                    $value = [];
                }
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $pruned[$key] = $value;
        }

        return $isList ? array_values($pruned) : $pruned;
    }
}
