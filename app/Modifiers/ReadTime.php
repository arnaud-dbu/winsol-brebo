<?php

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

class ReadTime extends Modifier
{
    /**
     * Calculate the estimated read time for content.
     *
     * @param mixed $value The content to calculate read time for
     * @param array $params Optional parameters [0] = words per minute (default: 200)
     * @param array $context The template context
     * @return int The estimated read time in minutes
     */
    public function index($value, $params, $context)
    {
        $wordsPerMinute = $params[0] ?? 200;

        // Handle both HTML and plain text
        $text = strip_tags($value);

        // Count words
        $wordCount = str_word_count($text);

        // Calculate minutes, minimum 1
        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }
}
