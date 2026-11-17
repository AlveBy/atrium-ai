<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Result;
use Atrium\Ai\Results;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Where a generation leaves its answer: in the cache, for the one who asked.
 */
final class ResultsTest extends TestCase
{
    public function test_a_started_generation_is_pending_and_only_its_owner_sees_it(): void
    {
        $id = app(Results::class)->start(7);

        $this->assertSame(['status' => 'pending'], app(Results::class)->find($id, 7));
        $this->assertNull(app(Results::class)->find($id, 8));
        $this->assertNull(app(Results::class)->find($id, null));
        $this->assertNull(app(Results::class)->find('unknown', 7));
        $this->assertSame(['owner' => 7, 'status' => 'pending'], Cache::get('atrium-ai:result:'.$id));
    }

    public function test_an_answer_and_a_failure_keep_the_owner(): void
    {
        $results = app(Results::class);
        $done = $results->start(7);
        $failed = $results->start(null);

        $results->done($done, new Result(value: 'Text', provider: 'gemini', model: 'flash'));
        $results->failed($failed, 'Busy');

        $this->assertSame(['status' => 'done', 'value' => 'Text', 'provider' => 'gemini', 'model' => 'flash'], $results->find($done, 7));
        $this->assertSame(['status' => 'failed', 'message' => 'Busy'], $results->find($failed, null));
        $this->assertNull($results->find($done, null));
    }

    public function test_an_answer_to_an_expired_generation_is_dropped(): void
    {
        app(Results::class)->done('gone', new Result(value: 'Text'));

        $this->assertNull(app(Results::class)->find('gone', null));
        $this->assertNull(Cache::get('atrium-ai:result:gone'));
    }

    public function test_an_answer_is_kept_for_the_ttl_of_the_config_and_ten_minutes_without_one(): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        $results = app(Results::class);

        config(['atrium-ai.result_ttl' => 30]);
        $short = $results->start(1);
        $this->forgetConfig('atrium-ai.result_ttl');
        $long = $results->start(1);

        Carbon::setTestNow('2026-01-01 00:00:29');
        $this->assertNotNull($results->find($short, 1));
        Carbon::setTestNow('2026-01-01 00:00:31');
        $this->assertNull($results->find($short, 1));

        Carbon::setTestNow('2026-01-01 00:09:59');
        $this->assertNotNull($results->find($long, 1));
        Carbon::setTestNow('2026-01-01 00:10:01');
        $this->assertNull($results->find($long, 1));
    }

    public function test_only_what_a_result_has_is_sent(): void
    {
        $this->assertSame(['value' => 'Text'], (new Result(value: 'Text'))->toArray());
        $this->assertSame(['values' => ['de' => 'Text'], 'provider' => 'groq', 'model' => 'llama'], (new Result(values: ['de' => 'Text'], provider: 'groq', model: 'llama'))->toArray());
        $this->assertSame([], (new Result)->toArray());
    }
}
