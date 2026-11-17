<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Models\Usage;
use Atrium\Ai\Providers;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * `atrium:ai:status`: the chains as written, with or without keys, and the
 * usage of the last days.
 */
final class StatusCommandTest extends TestCase
{
    public function test_the_chains_say_which_providers_have_a_key(): void
    {
        config(['atrium-ai.chains.text' => 'gemini,openrouter:meta-llama/x:free,nosuch']);

        $output = $this->runStatus();

        $this->assertMatchesRegularExpression('/\| text +\| gemini +\| \(default\) +\| yes +\|/', $output);
        $this->assertMatchesRegularExpression('/\| text +\| openrouter +\| meta-llama\/x:free +\| no key +\|/', $output);
        // Not set up in config/ai.php at all.
        $this->assertMatchesRegularExpression('/\| text +\| nosuch +\| \(default\) +\| no key +\|/', $output);
        $this->assertStringNotContainsString('No text provider has a key', $output);
    }

    public function test_a_provider_that_is_not_set_up_is_never_asked(): void
    {
        config(['atrium-ai.chains.text' => 'nosuch,gemini']);

        $this->assertSame(['gemini' => null], app(Providers::class)->chain('text'));
        $this->assertSame([['provider' => 'nosuch', 'model' => null, 'usable' => false], ['provider' => 'gemini', 'model' => null, 'usable' => true]], app(Providers::class)->describe('text'));
    }

    public function test_without_a_text_provider_it_warns_that_the_buttons_are_hidden(): void
    {
        config(['ai.providers.gemini.key' => null, 'ai.providers.groq.key' => null]);

        $this->assertStringContainsString('No text provider has a key: the "Generate" buttons are hidden.', $this->runStatus());
    }

    public function test_the_usage_of_the_last_days_by_provider(): void
    {
        $this->travelTo(now()->startOfMinute());
        Usage::query()->create(['task' => 'describe', 'provider' => 'gemini', 'input_tokens' => 10, 'output_tokens' => 20, 'duration_ms' => 100]);
        Usage::query()->create(['task' => 'describe', 'provider' => 'gemini', 'input_tokens' => 5, 'output_tokens' => 0, 'duration_ms' => 201, 'succeeded' => false]);
        Usage::query()->create(['task' => 'describe', 'provider' => null, 'duration_ms' => 7, 'succeeded' => false]);
        $old = Usage::query()->create(['task' => 'describe', 'provider' => 'groq', 'input_tokens' => 99]);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        $yesterday = Usage::query()->create(['task' => 'describe', 'provider' => 'groq', 'input_tokens' => 3, 'output_tokens' => 4, 'duration_ms' => 50]);
        $yesterday->forceFill(['created_at' => now()->subDays(2)])->save();

        $tonight = Usage::query()->create(['task' => 'describe', 'provider' => 'ollama', 'duration_ms' => 9]);
        $tonight->forceFill(['created_at' => now()->subHours(12)])->save();

        $output = $this->runStatus();

        $this->assertMatchesRegularExpression('/\| \(none answered\) +\| 1 +\| 1 +\| 0 +\| 0 +\| 7 +\|/', $output);
        $this->assertMatchesRegularExpression('/\| gemini +\| 2 +\| 1 +\| 15 +\| 20 +\| 151 +\|/', $output);
        $this->assertMatchesRegularExpression('/\| groq +\| 1 +\| 0 +\| 3 +\| 4 +\| 50 +\|/', $output);
        $this->assertStringNotContainsString('99', $output);

        // A shorter period, and never less than a day.
        // The chains above list groq too: only a row with numbers is usage.
        $this->assertDoesNotMatchRegularExpression('/\| groq +\| \d+ +\|/', $this->runStatus(['--days' => 1]));
        $this->assertDoesNotMatchRegularExpression('/\| groq +\| \d+ +\|/', $this->runStatus(['--days' => 0]));
        $this->assertMatchesRegularExpression('/\| ollama +\| 1 +\| 0 +\| 0 +\| 0 +\| 9 +\|/', $this->runStatus(['--days' => 0]));
        $this->assertMatchesRegularExpression('/\| ollama +\| 1 +\|/', $this->runStatus(['--days' => -5]));
    }

    public function test_before_the_module_is_installed_it_says_how_to_install_it(): void
    {
        $this->dropTheUsageLog();

        $output = $this->runStatus();

        $this->assertStringContainsString('No usage log yet: run php artisan atrium:module:install alveby/atrium-ai.', $output);
        $this->assertStringNotContainsString('Avg ms', $output);
    }

    /**
     * @param  array<string,mixed>  $options
     */
    private function runStatus(array $options = []): string
    {
        $this->assertSame(0, Artisan::call('atrium:ai:status', $options));

        return Artisan::output();
    }

    /**
     * MySQL commits a dropped table at once, past the test's transaction:
     * the next test migrates the database again.
     */
    private function dropTheUsageLog(): void
    {
        Schema::drop('ai_usage');
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });
    }
}
