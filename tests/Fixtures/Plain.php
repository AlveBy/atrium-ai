<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * An administrable model that is not translatable.
 */
final class Plain extends Model implements Administrable
{
    use InteractsWithAdmin;

    protected $table = 'ai_articles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['name' => 'array'];
    }

    public static function getAdminLabel(): string
    {
        return 'Plain';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'plain';
    }

    /**
     * Half of what Translatable gives: a model with one of the two methods
     * is not a translatable one.
     *
     * @return array<int,string>
     */
    public function getTranslatableAttributes(): array
    {
        return ['name'];
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('name')->label('Name')->ai('describe'), TextField::make('detail_text')->label('Detail')->ai('describe')];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
