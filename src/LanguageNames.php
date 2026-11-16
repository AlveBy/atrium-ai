<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

/**
 * How a content language is named to the model: in English, with its
 * script where the language is written in more than one ("Uzbek in Latin
 * script"), next to the name the site gave it and its code. A bare "Oʻzbekcha"
 * or "kk" leaves the model to guess the script.
 */
final class LanguageNames
{
    /** By content-language code, then by its first part. */
    private const NAMES = [
        'en' => 'English',
        'ru' => 'Russian',
        'uk' => 'Ukrainian',
        'be' => 'Belarusian in Cyrillic script',
        'kk' => 'Kazakh in Cyrillic script',
        'uz' => 'Uzbek in Latin script',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'pt' => 'Portuguese',
        'pt-br' => 'Brazilian Portuguese',
        'it' => 'Italian',
        'pl' => 'Polish',
        'tr' => 'Turkish',
        'zh' => 'Simplified Chinese',
        'zh-cn' => 'Simplified Chinese',
        'zh-hans' => 'Simplified Chinese',
        'zh-hant' => 'Traditional Chinese',
        'zh-tw' => 'Traditional Chinese',
        'zh-hk' => 'Traditional Chinese',
        'zh-mo' => 'Traditional Chinese',
        'ja' => 'Japanese',
    ];

    /** A script named in the code ("uz-cyrl") wins over the usual one. */
    private const SCRIPTS = [
        'latn' => 'Latin',
        'cyrl' => 'Cyrillic',
    ];

    public static function describe(string $code, ?string $name = null): string
    {
        $key = str_replace('_', '-', strtolower($code));
        [$base, $suffix] = array_pad(explode('-', $key, 2), 2, null);
        $english = self::NAMES[$key] ?? null;

        if ($english === null && isset(self::NAMES[$base], self::SCRIPTS[$suffix])) {
            $english = preg_replace('/ in \w+ script$/', '', self::NAMES[$base]).' in '.self::SCRIPTS[$suffix].' script';
        }

        $english ??= self::NAMES[$base] ?? null;
        $own = $name !== null && trim($name) !== '' && $name !== $code && $name !== $english ? trim($name) : null;

        if ($english === null) {
            return $own !== null ? "{$own} ({$code})" : $code;
        }

        return $own !== null ? "{$english} ({$own}, {$code})" : "{$english} ({$code})";
    }
}
