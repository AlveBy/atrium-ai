<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Help\Tools;

use Atrium\Admin\Models\AdminUser;
use Atrium\Ai\Help\Records;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetRecord implements Tool
{
    public function __construct(private readonly AdminUser $user) {}

    public function name(): string
    {
        return 'get_record';
    }

    public function description(): string
    {
        return 'One record of a module with all its fields and its admin_url.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()->description('Module key from list_modules.')->required(),
            'id' => $schema->integer()->description('Record id.')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return app(Records::class)->get($this->user, (string) $request->string('module'), (int) $request->integer('id'));
    }
}
