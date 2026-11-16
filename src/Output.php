<?php

declare(strict_types=1);

namespace Atrium\Ai;

use Illuminate\Support\Str;

/**
 * Tidies a model's answer: models like to wrap it in quotes or a code block,
 * or to put a "Title:" in front, however often they are asked not to.
 */
final class Output
{
    public static function clean(string $text, string $format = 'text', ?int $max = null): string
    {
        $text = trim($text);

        // ```html … ``` around the whole answer.
        if (preg_match('/^```[a-z]*\s*\n(.*)\n```$/is', $text, $match) === 1) {
            $text = trim($match[1]);
        }

        // "…", «…», '…' around the whole answer.
        if (preg_match('/^(["«“\'])(.*)(["»”\'])$/su', $text, $match) === 1 && ! str_contains($match[2], $match[1])) {
            $text = trim($match[2]);
        }

        if ($format === 'text') {
            $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
        }

        if ($max !== null && $max > 0 && mb_strlen($text) > $max) {
            $text = rtrim(Str::limit($text, $max - 1, ''), ' ,.;:-').'…';
        }

        return $text;
    }
}
