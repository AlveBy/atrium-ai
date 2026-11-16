<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Jobs;

use Atrium\Ai\Assistant;
use Atrium\Ai\Brief;
use Atrium\Ai\Failures;
use Atrium\Ai\Results;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/**
 * One "Generate" click, off the request: the form polls for the result.
 */
final class Generate implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** The provider chain already retries on the next provider. */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly string $id, public readonly Brief $brief)
    {
        // A translation asks once per language: fifteen languages are fourteen calls.
        $this->timeout = max($this->timeout, (count($brief->targets) + 1) * (int) config('atrium-ai.timeout', 60));
    }

    public function handle(Assistant $assistant, Results $results): void
    {
        try {
            $results->done($this->id, $assistant->generate($this->brief));
        } catch (Throwable $e) {
            $results->failed($this->id, Failures::message($e));
        }
    }
}
