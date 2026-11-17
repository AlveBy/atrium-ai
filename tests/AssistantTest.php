<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Assistant;
use Atrium\Ai\Brief;
use Atrium\Ai\Exceptions\NotConfigured;
use Atrium\Ai\Failures;
use Atrium\Ai\Models\Usage;
use Atrium\Ai\Task;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The assistant on its own: vectors for search, what happens when no
 * provider has a key or one fails, and the usage log that must never cost
 * the editor the answer.
 */
final class AssistantTest extends TestCase
{
    public function test_texts_are_turned_into_vectors_by_the_embeddings_chain(): void
    {
        Embeddings::fake([[[0.1, 0.2], [0.3, 0.4]]]);

        $this->assertSame([[0.1, 0.2], [0.3, 0.4]], app(Assistant::class)->embed(['Lake', 'Hotel']));

        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->inputs === ['Lake', 'Hotel'] && $prompt->provider->name() === 'gemini');
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function noEmbeddings(): array
    {
        return [
            'no key for the provider' => [['ai.providers.gemini.key' => null]],
            'an empty key' => [['ai.providers.gemini.key' => '  ']],
            'an empty chain' => [['atrium-ai.chains.embeddings' => '']],
            'a provider that is not set up' => [['atrium-ai.chains.embeddings' => 'nosuch']],
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     */
    #[DataProvider('noEmbeddings')]
    public function test_without_an_embeddings_provider_nothing_is_sent(array $config): void
    {
        Embeddings::fake()->preventStrayEmbeddings();
        config($config);

        try {
            app(Assistant::class)->embed(['Lake']);
            $this->fail('No provider: the assistant should refuse.');
        } catch (NotConfigured $e) {
            $this->assertSame('No AI provider with a key for "embeddings": set one in .env (see config/atrium-ai.php).', $e->getMessage());
        }

        Embeddings::assertNothingGenerated();
    }

    public function test_without_a_text_provider_nothing_is_asked_or_logged(): void
    {
        Writer::fake()->preventStrayPrompts();
        config(['ai.providers.gemini.key' => null, 'ai.providers.groq.key' => '']);

        try {
            app(Assistant::class)->generate($this->brief());
            $this->fail('No provider: the assistant should refuse.');
        } catch (NotConfigured $e) {
            $this->assertSame('No AI provider with a key for "text": set one in .env (see config/atrium-ai.php).', $e->getMessage());
        }

        Writer::assertNeverPrompted();
        $this->assertSame(0, Usage::query()->count());
    }

    public function test_a_failed_call_is_logged_as_failed_and_thrown_on(): void
    {
        Writer::fake(fn () => throw new RuntimeException('Connection timed out'));

        try {
            app(Assistant::class)->generate($this->brief());
            $this->fail('The failure should reach the caller.');
        } catch (RuntimeException $e) {
            $this->assertSame('Connection timed out', $e->getMessage());
        }

        $usage = Usage::query()->sole();
        $this->assertFalse($usage->succeeded);
        $this->assertNull($usage->provider);
        $this->assertSame(0, $usage->input_tokens);
        $this->assertSame('describe', $usage->task);
        $this->assertSame('articles', $usage->module);
        $this->assertSame(7, $usage->admin_user_id);
    }

    public function test_with_the_log_off_nothing_is_kept(): void
    {
        Writer::fake(['A cosy hotel.']);
        config(['atrium-ai.log' => false]);

        $this->assertSame('A cosy hotel.', app(Assistant::class)->generate($this->brief())->value);
        $this->assertSame(0, Usage::query()->count());
    }

    public function test_a_log_that_cannot_be_written_does_not_cost_the_answer(): void
    {
        Exceptions::fake();
        Writer::fake(['A cosy hotel.']);
        $this->dropTheUsageLog();

        $result = app(Assistant::class)->generate($this->brief());

        $this->assertSame('A cosy hotel.', $result->value);
        $this->assertSame('gemini', $result->provider);
        Exceptions::assertReported(QueryException::class);
    }

    public function test_a_missing_key_is_said_plainly_and_not_reported(): void
    {
        Exceptions::fake();

        $this->assertSame(__('atrium-ai::ai.errors.not_configured'), Failures::message(NotConfigured::for('text')));
        Exceptions::assertNothingReported();
    }

    public function test_a_busy_provider_and_a_broken_one_are_reported_with_their_own_message(): void
    {
        Exceptions::fake();

        $this->assertSame(__('atrium-ai::ai.errors.busy'), Failures::message(RateLimitedException::forProvider('gemini', 429)));
        $this->assertSame(__('atrium-ai::ai.errors.failed'), Failures::message(new RuntimeException('Connection timed out')));

        Exceptions::assertReported(RateLimitedException::class);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Connection timed out');
    }

    private function brief(): Brief
    {
        return new Brief(Task::Describe, 'en', ['name' => 'Lake Hotel'], 'detail_text', module: 'articles', user: 7);
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
