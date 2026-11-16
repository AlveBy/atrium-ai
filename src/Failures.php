<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Atrium\Ai\Exceptions\NotConfigured;
use Laravel\Ai\Exceptions\FailoverableException;
use Throwable;

/**
 * What the editor reads when a generation fails. Provider errors stay in the
 * log: they may quote the request.
 */
final class Failures
{
    public static function message(Throwable $e): string
    {
        if ($e instanceof NotConfigured) {
            return __('atrium-ai::ai.errors.not_configured');
        }

        report($e);

        return $e instanceof FailoverableException
            ? __('atrium-ai::ai.errors.busy')
            : __('atrium-ai::ai.errors.failed');
    }
}
