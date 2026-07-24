<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

class Highlight extends Modifier
{
    /**
     * Cached SVG content for scribbles
     */
    private static array $svgCache = [];

    public function index($value, $params, $context)
    {
        // Convert Bard/array fields to plain text
        if (is_array($value)) {
            $value = $this->convertBardToPlainText($value);
        }

        // Convert objects to string
        if (is_object($value)) {
            $value = (string) $value;
        }

        // Ensure we have a string to work with
        if (!is_string($value)) {
            return $value;
        }

        $highlightType = $this->getContextValue($context, 'highlight', 'color');
        $scribbleStyle = $this->getContextValue($context, 'style', 'underline');

        if ($highlightType === 'scribble') {
            return $this->applyScribble($value, $scribbleStyle);
        }

        return $this->applyColor($value);
    }

    /**
     * Convert Bard/ProseMirror array to plain text
     */
    private function convertBardToPlainText(array $bardContent): string
    {
        $text = '';

        foreach ($bardContent as $node) {
            if (!is_array($node)) {
                continue;
            }

            // Handle paragraph/heading nodes
            if (isset($node['content']) && is_array($node['content'])) {
                foreach ($node['content'] as $contentNode) {
                    if (isset($contentNode['text'])) {
                        $text .= $contentNode['text'];
                    }
                }
            }
        }

        return $text;
    }

    /**
     * Get a value from context, handling both Value objects and plain strings
     */
    private function getContextValue(array $context, string $key, string $default): string
    {
        if (!isset($context[$key])) {
            return $default;
        }

        $value = $context[$key];

        // Handle Statamic Value objects - cast to string which triggers __toString()
        if (is_object($value)) {
            $value = (string) $value;
        }

        // Handle arrays (e.g., from select fields)
        if (is_array($value)) {
            $value = $value[0] ?? $default;
        }

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Apply color highlight (text-mint)
     */
    private function applyColor(string $value): string
    {
        return preg_replace(
            '/\*([^*]+)\*/',
            '<span class="text-mint">$1</span>',
            $value
        );
    }

    /**
     * Apply scribble highlight with SVG decoration per word
     */
    private function applyScribble(string $value, string $style): string
    {
        $svg = $this->getSvg($style);

        return preg_replace_callback('/\*([^*]+)\*/', function ($matches) use ($style, $svg) {
            $text = $matches[1];
            $words = preg_split('/\s+/', $text);

            $wrappedWords = array_map(function ($word) use ($style, $svg) {
                return sprintf(
                    '<span class="scribble-wrapper scribble-%s">%s%s</span>',
                    $style,
                    e($word),
                    $svg
                );
            }, $words);

            return implode(' ', $wrappedWords);
        }, $value);
    }

    /**
     * Get SVG content for the given scribble style
     */
    private function getSvg(string $style): string
    {
        if (!isset(self::$svgCache[$style])) {
            $path = resource_path("svg/scribbles/{$style}.svg");

            if (!file_exists($path)) {
                self::$svgCache[$style] = '';
                return '';
            }

            $svg = file_get_contents($path);

            // Add preserveAspectRatio="none" for proper scaling
            $svg = preg_replace(
                '/<svg([^>]*)>/',
                '<svg$1 preserveAspectRatio="none">',
                $svg
            );

            self::$svgCache[$style] = $svg;
        }

        return self::$svgCache[$style];
    }
}
