<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\ContentField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\SlugField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property array<string,string>|string $name
 */
class Article extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'ai_articles';

    protected $guarded = [];

    /** @var array<int,string> */
    protected array $translatable = ['name', 'preview_text'];

    public static function getAdminLabel(): string
    {
        return 'Articles';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'articles';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            SlugField::make('code')->label('Code')->from('name'),
            TextareaField::make('preview_text')->label('Preview')->ai('preview', from: 'detail_text'),
            ContentField::make('detail_text')->label('Description')->ai('describe'),
            ImageField::make('picture')->label('Picture'),
            TextField::make('picture_alt')->label('Picture alt')->ai('alt', from: 'picture'),
            TextField::make('meta_title')->label('Meta title')->maxLength(40)->ai('seo_title'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
