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

final class ListModules implements Tool
{
    public function __construct(private readonly AdminUser $user) {}

    public function name(): string
    {
        return 'list_modules';
    }

    public function description(): string
    {
        return 'The modules (sections) of this admin panel the admin may open: key, menu label and admin address.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return (string) json_encode(app(Records::class)->modules($this->user), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
