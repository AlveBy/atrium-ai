<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests\Fixtures;

use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Traits\HasAdminTabs;

/**
 * An article of the panel with tabs: the SEO fields are added to its own.
 */
final class SeoArticle extends Article
{
    use HasAdminTabs;

    public static function getSeoAdminFields(): array
    {
        return [TextareaField::make('meta_description')->label('Meta description')->ai('seo_description')];
    }
}
