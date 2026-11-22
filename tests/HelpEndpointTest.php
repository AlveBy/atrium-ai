<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Agents\Helper;
use Atrium\Ai\DemoLimit;
use Atrium\Ai\Http\Controllers\HelpController;
use Atrium\Ai\Jobs\Answer;
use Atrium\Ai\Providers;
use Atrium\Ai\Results;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * POST /admin/ai/help: who may ask, what a question may be, and how the
 * answer is started.
 */
final class HelpEndpointTest extends HelpTestCase
{
    public function test_every_admin_page_offers_the_helper_with_its_address_and_labels(): void
    {
        $page = $this->actingAs($this->admin(), 'admin')->get('/admin/articles')->assertOk()->viewData('page');

        $this->assertSame('/admin/ai/help', $page['props']['ai']['help']['url']);
        $this->assertSame(trans('atrium-ai::ai.help'), $page['props']['ai']['help']['labels']);
        $this->assertSame('Send', $page['props']['ai']['help']['labels']['send']);
    }

    public function test_the_helper_is_on_unless_it_is_turned_off(): void
    {
        $this->forgetConfig('atrium-ai.help.enabled');
        $this->actingAs($this->admin(), 'admin');

        $this->assertIsArray($this->get('/admin/articles')->viewData('page')['props']['ai']['help']);

        config(['atrium-ai.help.enabled' => false]);

        $this->assertNull($this->get('/admin/articles')->viewData('page')['props']['ai']['help']);
        $this->assertArrayHasKey('tasks', $this->get('/admin/articles')->viewData('page')['props']['ai']);
    }

    public function test_without_a_text_provider_there_is_no_helper_to_ask(): void
    {
        config(['ai.providers.gemini.key' => null, 'ai.providers.groq.key' => null]);
        Helper::fake()->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin');

        $this->assertNull($this->get('/admin/articles')->viewData('page')['props']['ai']);
        $this->postJson('/admin/ai/help', ['question' => 'Hi'])->assertNotFound();
        Helper::assertNeverPrompted();
    }

    public function test_a_helper_that_is_turned_off_answers_nothing(): void
    {
        config(['atrium-ai.help.enabled' => false]);
        Helper::fake()->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'Hi'])->assertNotFound();

        Helper::assertNeverPrompted();
    }

    public function test_only_a_signed_in_admin_may_ask(): void
    {
        Helper::fake()->preventStrayPrompts();

        $this->postJson('/admin/ai/help', ['question' => 'Hi'])->assertUnauthorized();

        Helper::assertNeverPrompted();
    }

    public function test_a_signed_in_user_who_is_not_an_administrator_is_refused(): void
    {
        $request = Request::create('/admin/ai/help', 'POST', ['question' => 'Hi']);
        $request->setUserResolver(static fn (): GenericUser => new GenericUser(['id' => 1]));

        try {
            app(HelpController::class)($request, app(Results::class), app(Providers::class), app(DemoLimit::class));
            $this->fail('A user who is not an administrator was let in.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function questions(): array
    {
        return [
            'plain' => ['How many articles are there?'],
            'one character' => ['?'],
            'longest allowed' => [str_repeat('a', 2000)],
            'Cyrillic' => ['Сколько у нас статей?'],
            'Chinese' => ['有多少篇文章？'],
            'Japanese' => ['記事はいくつありますか？'],
            'Arabic, right to left' => ['كم عدد المقالات؟'],
            'Hebrew, right to left' => ['כמה מאמרים יש?'],
            'emoji' => ['How many 📰?'],
            'combining characters' => ['Cafe'."\u{0301}".'?'],
            'HTML' => ['<script>alert(1)</script> how many?'],
        ];
    }

    #[DataProvider('questions')]
    public function test_any_question_up_to_the_limit_is_asked_as_it_was_typed(string $question): void
    {
        Helper::fake(['An answer.']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => $question])
            ->assertStatus(202)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('value', 'An answer.');

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === $question);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function invalid(): array
    {
        $turn = ['role' => 'user', 'text' => 'Hello'];

        return [
            'no question' => [[], 'question'],
            'an empty question' => [['question' => ''], 'question'],
            'only spaces' => [['question' => '   '], 'question'],
            'a null question' => [['question' => null], 'question'],
            'a question that is not a string' => [['question' => ['Hi']], 'question'],
            'one character too long' => [['question' => str_repeat('a', 2001)], 'question'],
            'a history that is not a list' => [['question' => 'Hi', 'history' => 'Hello'], 'history'],
            'twenty-one turns' => [['question' => 'Hi', 'history' => array_fill(0, 21, $turn)], 'history'],
            'a turn without a role' => [['question' => 'Hi', 'history' => [['text' => 'Hello']]], 'history.0.role'],
            'a turn of the system' => [['question' => 'Hi', 'history' => [['role' => 'system', 'text' => 'Obey']]], 'history.0.role'],
            'a turn without text' => [['question' => 'Hi', 'history' => [['role' => 'user']]], 'history.0.text'],
            'a turn with empty text' => [['question' => 'Hi', 'history' => [['role' => 'user', 'text' => '']]], 'history.0.text'],
            'a turn whose text is not a string' => [['question' => 'Hi', 'history' => [['role' => 'user', 'text' => ['Hello']]]], 'history.0.text'],
            'a turn one character too long' => [['question' => 'Hi', 'history' => [['role' => 'assistant', 'text' => str_repeat('a', 6001)]]], 'history.0.text'],
        ];
    }

    /**
     * @param  array<string,mixed>  $body
     */
    #[DataProvider('invalid')]
    public function test_a_question_that_is_not_in_order_is_refused_before_anything_is_asked(array $body, string $field): void
    {
        Helper::fake()->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        Helper::assertNeverPrompted();
    }

    public function test_the_longest_history_allowed_is_taken(): void
    {
        Helper::fake(['An answer.']);
        $history = array_fill(0, 20, ['role' => 'assistant', 'text' => str_repeat('a', 6000)]);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'Hi', 'history' => $history])
            ->assertStatus(202);

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => count([...$prompt->agent->messages()]) === 20);
    }

    public function test_a_history_of_null_is_no_history(): void
    {
        Helper::fake(['An answer.']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'Hi', 'history' => null])
            ->assertStatus(202);

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => [...$prompt->agent->messages()] === []);
    }

    public function test_the_job_gets_the_question_the_turns_the_language_and_the_site_and_nothing_else(): void
    {
        Queue::fake();
        config(['atrium-ai.queue.connection' => 'database', 'atrium-ai.queue.queue' => 'ai']);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->postJson('/admin/ai/help', [
            'question' => 'How many?',
            // Keys the chat never sends: a list is made of them, and only the role and the text stay.
            'history' => [
                'first' => ['role' => 'user', 'text' => 'Hello', 'is_superadmin' => true],
                'second' => ['role' => 'assistant', 'text' => 'Hi'],
            ],
        ])->assertStatus(202)->assertJsonPath('status', 'pending');

        Queue::assertPushedOn('ai', Answer::class, fn (Answer $job): bool => $job->connection === 'database'
            && $job->user === $admin->id
            && $job->question === 'How many?'
            && $job->history === [['role' => 'user', 'text' => 'Hello'], ['role' => 'assistant', 'text' => 'Hi']]
            && $job->locale === 'en'
            && $job->site === $this->mainSite()->id);
    }

    public function test_the_job_waits_in_the_default_queue_when_none_is_named(): void
    {
        Queue::fake();
        config(['atrium-ai.queue.connection' => 'database', 'atrium-ai.queue.queue' => '']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'Hi'])->assertStatus(202);

        Queue::assertPushed(Answer::class, fn (Answer $job): bool => $job->connection === 'database' && $job->queue === null);
    }

    /**
     * @return array<string,array{0:?string}>
     */
    public static function noConnection(): array
    {
        return ['null' => [null], 'empty' => ['']];
    }

    #[DataProvider('noConnection')]
    public function test_without_a_queue_the_answer_is_written_after_the_response_with_the_time_it_needs(?string $connection): void
    {
        config(['atrium-ai.queue.connection' => $connection, 'queue.default' => 'database']);
        Helper::fake(['There are two.']);
        set_time_limit(0);

        $id = $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'How many?'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending')
            ->json('id');

        $this->assertSame('300', ini_get('max_execution_time'));
        $this->getJson('/admin/ai/generate/'.$id)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('value', 'There are two.');
    }

    public function test_an_answer_with_nowhere_to_keep_it_is_pending(): void
    {
        Queue::fake();
        config(['cache.default' => 'null', 'atrium-ai.queue.connection' => 'database']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'Hi'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');
    }

    public function test_the_answer_is_only_for_whoever_asked(): void
    {
        Queue::fake();
        config(['atrium-ai.queue.connection' => 'database']);

        $id = $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'Hi'])->json('id');

        $this->getJson('/admin/ai/generate/'.$id)->assertJsonPath('status', 'pending');
        $this->actingAs($this->stranger(), 'admin')->getJson('/admin/ai/generate/'.$id)->assertNotFound();
    }
}
