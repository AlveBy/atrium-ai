<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Modules\ModuleSettings;

/**
 * Modules → AI: whether the helper in the corner shows its moods (brows, a
 * smile, a magnifier while it searches) or stays the calm arch. One set for
 * all sites: it is the panel's look, not a site's.
 */
final class AiSettings extends ModuleSettings
{
    public function description(): string
    {
        return __('atrium-ai::ai.settings.description');
    }

    public function fields(): array
    {
        return [
            CheckboxField::make('playful')
                ->label(__('atrium-ai::ai.settings.playful'))
                ->hint(__('atrium-ai::ai.settings.playful_hint'))
                ->default(true),
        ];
    }
}
