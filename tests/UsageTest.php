<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Models\Usage;

final class UsageTest extends TestCase
{
    public function test_the_numbers_of_a_call_are_numbers_and_its_success_a_flag(): void
    {
        $usage = Usage::query()->create(['task' => 'describe', 'input_tokens' => '12', 'output_tokens' => '34', 'duration_ms' => '56', 'succeeded' => 1]);

        $this->assertSame(12, $usage->input_tokens);
        $this->assertSame(34, $usage->output_tokens);
        $this->assertSame(56, $usage->duration_ms);
        $this->assertTrue($usage->succeeded);
    }
}
