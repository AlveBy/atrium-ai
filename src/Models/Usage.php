<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One call to a provider: what, who, how long, how many tokens. The prompt
 * and the answer are never stored.
 *
 * @property int $id
 * @property string $task
 * @property string|null $module
 * @property int|null $admin_user_id
 * @property string|null $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $duration_ms
 * @property bool $succeeded
 */
class Usage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    protected $fillable = [
        'task', 'module', 'admin_user_id', 'provider', 'model',
        'input_tokens', 'output_tokens', 'duration_ms', 'succeeded',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
            'succeeded' => 'boolean',
        ];
    }
}
