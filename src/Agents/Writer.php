<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * The one agent behind every task: the instructions come from the "system"
 * prompt view, the task from its own view. Tests fake it with Writer::fake().
 */
final class Writer implements Agent
{
    use Promptable;

    public function __construct(private readonly string $instructions = '') {}

    public function instructions(): string
    {
        return $this->instructions;
    }
}
