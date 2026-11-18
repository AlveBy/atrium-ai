<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * The admin's helper: answers questions about the panel from the guides and
 * reads the installed modules' records through its tools. Tests fake it with
 * Helper::fake().
 */
final class Helper implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  list<Tool>  $tools
     * @param  list<Message>  $history  the conversation so far
     */
    public function __construct(
        private readonly string $instructions,
        private readonly array $tools = [],
        private readonly array $history = [],
    ) {}

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function tools(): iterable
    {
        return $this->tools;
    }

    public function messages(): iterable
    {
        return $this->history;
    }
}
