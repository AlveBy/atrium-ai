<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Jobs;

/**
 * Starts a job off the request. Without a queue connection the job runs in
 * this PHP process once the answer has gone to the browser: no worker
 * needed, and the page still does not wait. With one, a worker runs it.
 */
final class Background
{
    public static function run(Generate|Answer $job): void
    {
        $connection = config('atrium-ai.queue.connection');

        if ($connection === null || $connection === '') {
            app()->terminating(static function () use ($job): void {
                // A host may forbid it: the warning is silenced.
                @set_time_limit($job->timeout);

                app()->call([$job, 'handle']);
            });

            return;
        }

        $job->onConnection((string) $connection);

        if (($queue = config('atrium-ai.queue.queue')) !== null && $queue !== '') {
            $job->onQueue((string) $queue);
        }

        dispatch($job);
    }
}
