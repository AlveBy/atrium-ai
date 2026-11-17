<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Assistant;
use Atrium\Ai\Brief;
use Atrium\Ai\Models\Usage;
use Atrium\Ai\Task;
use Illuminate\Support\Carbon;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the model is told (the prompt views get the brief's data) and what is
 * logged of the answer.
 */
final class AssistantPromptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        view()->addNamespace('ai-probe', __DIR__.'/views');
        config(['atrium-ai.prompts.describe' => 'ai-probe::probe', 'atrium-ai.prompts.preview' => 'ai-probe::probe', 'atrium-ai.prompts.translate' => 'ai-probe::probe', 'atrium-ai.prompts.system' => 'ai-probe::system']);
    }

    /**
     * @return array<string,array{0:Task,1:?int,2:string}>
     */
    public static function limits(): array
    {
        return [
            'no limit at all' => [Task::Describe, null, 'none'],
            'only the field has one' => [Task::Describe, 50, '50'],
            'only the task has one' => [Task::Preview, null, '300'],
            'the field is stricter than the task' => [Task::Preview, 100, '100'],
            'the task is stricter than the field' => [Task::Preview, 500, '300'],
        ];
    }

    #[DataProvider('limits')]
    public function test_the_prompt_is_given_the_shorter_of_the_two_limits(Task $task, ?int $max, string $expected): void
    {
        Writer::fake(['Text']);

        app(Assistant::class)->generate(new Brief($task, 'en', ['One' => 'first', 'Two' => 'second'], 'The field', 'html', $max));

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === "{$task->value}|The field|English (en)|{$expected}|html|first\n\nsecond|One;Two|-|-");
    }

    public function test_a_text_field_is_not_asked_for_html(): void
    {
        Writer::fake(['Text']);

        app(Assistant::class)->generate(new Brief(Task::Describe, 'en', ['One' => 'first'], 'The field'));

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, '|text|first|'));
    }

    public function test_the_system_prompt_names_the_site_and_the_language_by_the_sites_own_name(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Text']);

        app(Assistant::class)->generate(new Brief(Task::Describe, 'de', ['One' => 'first'], 'The field'));
        app(Assistant::class)->generate(new Brief(Task::Describe, 'fi', ['One' => 'first'], 'The field'));

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->instructions() === 'Demo shop|German (Deutsch, de)');
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->instructions() === 'Demo shop|fi');
    }

    public function test_a_translation_asks_once_per_language_and_names_it(): void
    {
        Writer::fake(fn (string $prompt): string => str_contains($prompt, 'German') ? 'Hallo' : 'Bonjour');

        $result = app(Assistant::class)->generate(new Brief(Task::Translate, 'en', ['One' => 'Hello'], 'The field', targets: ['de' => 'German (de)', 'fr' => 'French (fr)']));

        $this->assertSame(['de' => 'Hallo', 'fr' => 'Bonjour'], $result->values);
        $this->assertSame('gemini', $result->provider);
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_ends_with($prompt->prompt, '|German (de)|de'));
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_ends_with($prompt->prompt, '|French (fr)|fr'));
    }

    public function test_a_translation_into_no_language_is_an_empty_answer_from_nobody(): void
    {
        Writer::fake()->preventStrayPrompts();

        $result = app(Assistant::class)->generate(new Brief(Task::Translate, 'en', ['One' => 'Hello'], 'The field'));

        $this->assertSame([], $result->values);
        $this->assertNull($result->provider);
        $this->assertNull($result->model);
    }

    public function test_the_call_is_logged_with_what_the_provider_said_of_it(): void
    {
        Writer::fake(fn () => new AgentResponse('inv', 'Text', new TextUsage(11, 22), new Meta('groq', 'llama-x')));

        $result = app(Assistant::class)->generate(new Brief(Task::Describe, 'en', ['One' => 'first'], 'The field'));

        $usage = Usage::query()->sole();
        $this->assertSame(['groq', 'llama-x', 11, 22, true], [$usage->provider, $usage->model, $usage->input_tokens, $usage->output_tokens, $usage->succeeded]);
        $this->assertSame(['groq', 'llama-x'], [$result->provider, $result->model]);
    }

    public function test_a_failed_call_is_logged_without_provider_model_or_tokens(): void
    {
        Writer::fake(fn () => throw new \RuntimeException('Down'));

        try {
            app(Assistant::class)->generate(new Brief(Task::Describe, 'en', ['One' => 'first'], 'The field'));
        } catch (\RuntimeException) {
        }

        $usage = Usage::query()->sole();
        $this->assertSame([null, null, 0, 0, false], [$usage->provider, $usage->model, $usage->input_tokens, $usage->output_tokens, $usage->succeeded]);
    }

    /**
     * @return array<string,array{0:string,1:int}>
     */
    public static function durations(): array
    {
        return ['a quarter of a second and a bit' => ['00:00:00.250400', 250], 'a quarter of a second and more' => ['00:00:00.250600', 251]];
    }

    #[DataProvider('durations')]
    public function test_the_duration_is_logged_in_whole_milliseconds(string $end, int $expected): void
    {
        Carbon::setTestNow('2026-01-01 00:00:00');
        Writer::fake(function () use ($end): string {
            Carbon::setTestNow('2026-01-01 '.$end);

            return 'Text';
        });

        app(Assistant::class)->generate(new Brief(Task::Describe, 'en', ['One' => 'first'], 'The field'));

        $this->assertSame($expected, Usage::query()->sole()->duration_ms);
    }

    public function test_the_provider_is_given_the_time_of_the_config_and_a_minute_without_one(): void
    {
        Writer::fake(['Text']);

        config(['atrium-ai.timeout' => 25]);
        app(Assistant::class)->generate(new Brief(Task::Describe, 'en', ['One' => 'first'], 'The field'));
        $this->forgetConfig('atrium-ai.timeout');
        app(Assistant::class)->generate(new Brief(Task::Describe, 'en', ['One' => 'first'], 'The field'));

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->timeout === 25);
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->timeout === 60);
    }
}
