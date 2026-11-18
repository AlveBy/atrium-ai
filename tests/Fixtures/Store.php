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
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

/**
 * A module whose records belong to one site, for the helper's site tests.
 *
 * @property int $id
 * @property int $site_id
 * @property string $name
 */
class Store extends Model implements Administrable
{
    use BelongsToSite, InteractsWithAdmin;

    protected $table = 'ai_stores';

    protected $fillable = ['site_id', 'name'];

    public static function getAdminLabel(): string
    {
        return 'Stores';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'stores';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('name')->label('Name')->searchable()->sortable(),
        ];
    }
}
