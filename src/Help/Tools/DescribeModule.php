<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Help\Tools;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Models\AdminUser;
use Atrium\Ai\Help\Records;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class DescribeModule implements Tool
{
    public function __construct(private readonly AdminUser $user) {}

    public function name(): string
    {
        return 'describe_module';
    }

    public function description(): string
    {
        return 'The fields of a module as its form shows them: name, label, type, whether required, allowed values.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()->description('Module key from list_modules.')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $module = (string) $request->string('module');

        if (! app(Records::class)->mayView($this->user, $module)) {
            return 'No module "'.$module.'" you may view. See list_modules.';
        }

        return (string) json_encode(current(app(ApiDocumentation::class)->modules([$module])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
