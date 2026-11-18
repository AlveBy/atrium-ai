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

final class ListRecords implements Tool
{
    public function __construct(private readonly AdminUser $user) {}

    public function name(): string
    {
        return 'list_records';
    }

    public function description(): string
    {
        return 'Records of a module: search by text, filter by exact column values, sort. The answer has the total count and, for each record, its admin_url.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()->description('Module key from list_modules.')->required(),
            'search' => $schema->string()->description('Text to look for in the searchable columns.'),
            'filter' => $schema->object()->description('Exact column values, e.g. {"active": 1}.'),
            'sort' => $schema->string()->description('Column to sort by. Default: id.'),
            'direction' => $schema->string()->enum(['asc', 'desc'])->description('Default: asc.'),
            'per_page' => $schema->integer()->description('Records to return, 1 to 10. Default: 5.'),
        ];
    }

    public function handle(Request $request): string
    {
        // The API itself passes over what is missing or empty, and reads at least one.
        $query = [...$request->all(['search', 'filter', 'sort', 'direction']), 'per_page' => min(10, $request->integer('per_page') ?: 5)];

        return app(Records::class)->list($this->user, (string) $request->string('module'), $query);
    }
}
