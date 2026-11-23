<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

/**
 * What a "Generate" button can do. The value is what ->ai('…') takes.
 */
enum Task: string
{
    /** A description from the record's name and other fields. */
    case Describe = 'describe';

    /** A short preview text from the long one. */
    case Preview = 'preview';

    case SeoTitle = 'seo_title';

    case SeoDescription = 'seo_description';

    case SeoKeywords = 'seo_keywords';

    /** Alt text of an image: needs a provider that sees pictures. */
    case Alt = 'alt';

    /** The field's text in the site's other languages. */
    case Translate = 'translate';

    /** The text already in the field (or a piece of it), corrected and made clearer. */
    case Improve = 'improve';

    /**
     * Which provider chain answers: "text" or "vision".
     */
    public function kind(): string
    {
        return $this === self::Alt ? 'vision' : 'text';
    }

    /**
     * The length the prompt asks for, in characters; null = no limit.
     */
    public function limit(): ?int
    {
        return match ($this) {
            self::SeoTitle => 60,
            self::SeoDescription => 160,
            self::SeoKeywords => 255,
            self::Preview => 300,
            self::Alt => 125,
            default => null,
        };
    }
}
