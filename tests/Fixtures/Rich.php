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
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SlugField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\WysiwygField;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A model with every kind of field a brief reads from or writes to.
 */
final class Rich extends Model implements Administrable
{
    use InteractsWithAdmin, Translatable;

    protected $table = 'ai_articles';

    /** @var array<int,string> */
    protected array $translatable = ['name', 'detail_text'];

    public static function getAdminLabel(): string
    {
        return 'Rich';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'rich';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name'),
            SlugField::make('code')->label('Code')->from('name'),
            TextField::make('email')->label('Email')->email(),
            TextField::make('password')->label('Password')->password(),
            TextField::make('url')->label('Url')->url(),
            TextField::make('phone')->label('Phone')->tel(),
            TextareaField::make('summary')->label('Summary')->maxLength(80)->ai('preview'),
            ContentField::make('detail_text')->label('Detail')->ai('describe'),
            WysiwygField::make('body')->label('Body'),
            NumberField::make('qty')->label('Qty'),
            ImageField::make('picture')->label('Picture'),
            TextField::make('picture_alt')->label('Picture alt')->ai('alt', from: 'picture'),
            TextField::make('free')->label('Free')->ai('describe'),
            TextField::make('caption')->label('Caption')->maxLength(0)->ai('preview', from: 'name'),
            TextareaField::make('notes')->label('Notes')->ai('describe', from: ['nosuch', 'name', 'notes', 'detail_text']),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [TextColumn::make('name')->label('Name')];
    }
}
