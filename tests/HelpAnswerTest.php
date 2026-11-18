<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Support\Sites;
use Atrium\Ai\Agents\Helper;
use Atrium\Ai\Jobs\Answer;
use Atrium\Ai\Models\Usage;
use Atrium\Ai\Results;
use Atrium\Ai\Tests\Fixtures\CountVisitors;
use Atrium\Ai\Tests\Fixtures\Store;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The job that answers one question: what the model is given, what the
 * admin reads back and what is logged.
 */
final class HelpAnswerTest extends HelpTestCase
{
    public function test_the_model_gets_the_tools_the_question_and_the_conversation_in_order(): void
    {
        Helper::fake(['An answer.']);

        $this->answer('How many?', [
            ['role' => 'user', 'text' => 'Hello'],
            ['role' => 'assistant', 'text' => 'Hi, how can I help?'],
            ['role' => 'user', 'text' => 'Wait'],
        ]);

        Helper::assertPrompted(function (AgentPrompt $prompt): bool {
            $tools = array_map(static fn (Tool $tool): string => $tool->name(), [...$prompt->agent->tools()]);
            $history = array_map(static fn ($message): array => [$message::class, $message->content], [...$prompt->agent->messages()]);

            return $prompt->prompt === 'How many?'
                && $tools === ['list_modules', 'describe_module', 'list_records', 'get_record', 'search_docs']
                && $history === [
                    [UserMessage::class, 'Hello'],
                    [AssistantMessage::class, 'Hi, how can I help?'],
                    [UserMessage::class, 'Wait'],
                ];
        });
    }

    public function test_the_instructions_name_the_site_the_language_and_the_modules_the_admin_may_open(): void
    {
        view()->addNamespace('ai-probe', __DIR__.'/views');
        config(['atrium-ai.prompts.help' => 'ai-probe::help']);
        Helper::fake(['An answer.', 'An answer.']);

        $this->answer();
        $this->answer(user: $this->editor(['stores.view'])->id);

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->instructions() === $this->mainSite()->name.'|English (en)|articles,languages,sites,stores');
        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->instructions() === $this->mainSite()->name.'|English (en)|stores');
    }

    public function test_without_a_view_of_its_own_the_helper_is_told_its_rules(): void
    {
        $this->forgetConfig('atrium-ai.prompts.help');
        Helper::fake(['An answer.']);

        $this->answer();

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'You only read.')
            && str_contains($prompt->agent->instructions(), '- Articles (articles): /admin/articles'));
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function languages(): array
    {
        return [
            'with its own name' => ['uz', 'Answer in Uzbek in Latin script (Oʻzbekcha, uz)'],
            'with a country' => ['pt_BR', 'Answer in Brazilian Portuguese (Português (Brasil), pt_BR)'],
            'not among the panel languages' => ['fi', 'Answer in fi,'],
        ];
    }

    #[DataProvider('languages')]
    public function test_the_answer_is_in_the_language_of_the_admin(string $locale, string $expected): void
    {
        Helper::fake(fn (): string => app()->getLocale());

        $result = $this->answer(locale: $locale);

        $this->assertSame($locale, $result['value']);
        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), $expected));
    }

    public function test_the_language_of_the_process_is_given_back_after_the_answer(): void
    {
        Helper::fake(['An answer.']);
        app()->setLocale('ru');

        $this->answer(locale: 'de');

        $this->assertSame('ru', app()->getLocale());
    }

    public function test_the_language_is_given_back_after_a_failure_too(): void
    {
        Helper::fake(fn () => throw new RuntimeException('Down'));
        app()->setLocale('ru');

        $this->answer(locale: 'de');

        $this->assertSame('ru', app()->getLocale());
    }

    public function test_on_a_worker_the_records_of_the_other_sites_stay_hidden(): void
    {
        $second = $this->secondSite();
        Store::query()->create(['name' => 'Main street', 'site_id' => $this->mainSite()->id]);
        Store::query()->create(['name' => 'Harbour', 'site_id' => $second->id]);
        app(Sites::class)->forgetCurrent();
        $seen = [];
        Event::listen(ToolInvoked::class, function (ToolInvoked $event) use (&$seen): void {
            $seen[] = $event->result;
        });
        Helper::fake([new ToolCall('call-1', 'list_records', ['module' => 'stores']), 'One store.']);

        $this->assertSame('One store.', $this->answer(site: $second->id)['value']);

        $this->assertCount(1, $seen);
        $this->assertStringContainsString('Harbour', $seen[0]);
        $this->assertStringNotContainsString('Main street', $seen[0]);
        $this->assertSame(1, json_decode($seen[0], true)['meta']['total']);
    }

    public function test_the_instructions_name_the_site_of_the_question(): void
    {
        view()->addNamespace('ai-probe', __DIR__.'/views');
        config(['atrium-ai.prompts.help' => 'ai-probe::help']);
        Helper::fake(['An answer.']);

        $this->answer(site: $this->secondSite()->id);

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => str_starts_with($prompt->agent->instructions(), 'Second shop|'));
    }

    public function test_a_process_with_no_site_of_its_own_has_none_after_the_answer(): void
    {
        Helper::fake(['An answer.']);
        $sites = app(Sites::class);
        $sites->forgetCurrent();

        $this->answer(site: $this->secondSite()->id);

        $this->assertFalse($sites->isResolved());
    }

    public function test_a_process_that_was_on_a_site_is_on_it_again_after_the_answer(): void
    {
        Helper::fake(['An answer.']);
        $sites = app(Sites::class);
        $sites->setCurrent($this->mainSite());

        $this->answer(site: $this->secondSite()->id);

        $this->assertTrue($sites->isResolved());
        $this->assertSame('main', $sites->current()->code);
    }

    public function test_a_site_deleted_in_the_meantime_is_a_failure_that_asks_nothing(): void
    {
        Exceptions::fake();
        Helper::fake()->preventStrayPrompts();
        $site = $this->secondSite();
        $site->delete();
        $sites = app(Sites::class);
        $sites->forgetCurrent();

        $result = $this->answer(site: $site->id);

        $this->assertSame(['failed', __('atrium-ai::ai.errors.failed')], [$result['status'], $result['message']]);
        $this->assertFalse($sites->isResolved());
        Helper::assertNeverPrompted();
        // A record that is not found is not reported, unlike a broken job.
        Exceptions::assertNothingReported();
    }

    public function test_an_admin_deleted_in_the_meantime_is_a_failure_that_asks_nothing(): void
    {
        Exceptions::fake();
        Helper::fake()->preventStrayPrompts();
        $admin = $this->stranger();
        $id = app(Results::class)->start($admin->id);
        $admin->delete();

        app()->call([new Answer($id, $admin->id, 'Hi', [], 'en', $this->mainSite()->id), 'handle']);

        $this->assertSame('failed', app(Results::class)->find($id, $admin->id)['status']);
        Helper::assertNeverPrompted();
    }

    public function test_a_provider_that_fails_leaves_the_admin_a_message(): void
    {
        Exceptions::fake();
        Helper::fake(fn () => throw new RuntimeException('Down'));

        $result = $this->answer();

        $this->assertSame(['failed', __('atrium-ai::ai.errors.failed')], [$result['status'], $result['message']]);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Down');
    }

    public function test_the_answer_is_trimmed_and_names_who_wrote_it(): void
    {
        Helper::fake(fn () => new AgentResponse('inv', "  There are two.\n ", new TextUsage(11, 22), new Meta('groq', 'llama-x')));

        $result = $this->answer();

        $this->assertSame(['done', 'There are two.', 'groq', 'llama-x'], [$result['status'], $result['value'], $result['provider'], $result['model']]);
    }

    public function test_a_long_answer_is_cut_at_six_thousand_characters_not_bytes(): void
    {
        Helper::fake([str_repeat('я', 6001)]);

        $this->assertSame(str_repeat('я', 6000), $this->answer()['value']);
    }

    public function test_the_provider_is_given_the_time_of_the_config_and_a_minute_without_one(): void
    {
        Helper::fake(['An answer.', 'An answer.']);

        config(['atrium-ai.timeout' => '25']);
        $this->answer();
        $this->forgetConfig('atrium-ai.timeout');
        $this->answer();

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->timeout === 25);
        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->timeout === 60);
    }

    public function test_the_first_text_provider_with_a_key_is_asked(): void
    {
        config(['ai.providers.gemini.key' => null]);
        Helper::fake(['An answer.']);

        $this->answer();

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'groq');
    }

    public function test_the_call_is_logged_with_what_the_provider_said_of_it_and_never_the_texts(): void
    {
        Helper::fake(fn () => new AgentResponse('inv', 'A secret answer', new TextUsage(11, 22), new Meta('groq', 'llama-x')));
        $admin = $this->admin();

        $this->answer('A secret question', [['role' => 'user', 'text' => 'A secret turn']], user: $admin->id);

        $usage = Usage::query()->sole();
        $this->assertSame(
            ['help', $admin->id, 'groq', 'llama-x', 11, 22, true],
            [$usage->task, $usage->admin_user_id, $usage->provider, $usage->model, $usage->input_tokens, $usage->output_tokens, $usage->succeeded],
        );
        $this->assertStringNotContainsString('secret', (string) json_encode($usage->getAttributes()));
    }

    public function test_a_failed_call_is_logged_without_provider_model_or_tokens(): void
    {
        Exceptions::fake();
        Helper::fake(fn () => throw new RuntimeException('Down'));

        $this->answer();

        $usage = Usage::query()->sole();
        $this->assertSame(['help', null, null, 0, 0, false], [$usage->task, $usage->provider, $usage->model, $usage->input_tokens, $usage->output_tokens, $usage->succeeded]);
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
        Helper::fake(function () use ($end): string {
            Carbon::setTestNow('2026-01-01 '.$end);

            return 'An answer.';
        });

        $this->answer();

        $this->assertSame($expected, Usage::query()->sole()->duration_ms);
    }

    public function test_with_the_log_off_nothing_is_kept(): void
    {
        Helper::fake(['An answer.']);
        config(['atrium-ai.log' => false]);

        $this->assertSame('An answer.', $this->answer()['value']);
        $this->assertSame(0, Usage::query()->count());
    }

    public function test_the_log_is_on_unless_it_is_turned_off(): void
    {
        Helper::fake(['An answer.']);
        $this->forgetConfig('atrium-ai.log');

        $this->answer();

        $this->assertSame(1, Usage::query()->count());
    }

    public function test_a_log_that_cannot_be_written_does_not_cost_the_answer(): void
    {
        Exceptions::fake();
        Helper::fake(['An answer.']);
        Schema::drop('ai_usage');
        // MySQL commits a dropped table at once, past the test's transaction.
        $this->beforeApplicationDestroyed(static function (): void {
            RefreshDatabaseState::$migrated = false;
        });

        $result = $this->expectingAFailedStatement(fn (): array => $this->answer());

        $this->assertSame(['done', 'An answer.'], [$result['status'], $result['value']]);
        Exceptions::assertReported(QueryException::class);
    }

    public function test_a_tool_the_model_calls_is_run_and_its_answer_handed_back(): void
    {
        $seen = [];
        Event::listen(ToolInvoked::class, function (ToolInvoked $event) use (&$seen): void {
            $seen[] = [$event->tool->name(), $event->result];
        });
        Helper::fake([new ToolCall('call-1', 'list_modules', []), 'Two modules.']);

        $this->assertSame('Two modules.', $this->answer()['value']);

        $this->assertSame([['list_modules', '[{"module":"articles","label":"Articles","admin_url":"/admin/articles"},{"module":"languages","label":"Languages","admin_url":"/admin/languages"},{"module":"sites","label":"Sites","admin_url":"/admin/sites"},{"module":"stores","label":"Stores","admin_url":"/admin/stores"}]']], $seen);
    }

    public function test_other_packages_add_their_tools_made_for_the_admins_language(): void
    {
        config(['atrium-ai.help.tools' => [CountVisitors::class]]);
        Helper::fake(['Seven.']);

        $this->answer(locale: 'de');

        Helper::assertPrompted(function (AgentPrompt $prompt): bool {
            $tools = [...$prompt->agent->tools()];

            return count($tools) === 6 && $tools[5] instanceof CountVisitors && $tools[5]->locale === 'de';
        });
    }

    public function test_a_single_tool_may_be_named_without_a_list(): void
    {
        config(['atrium-ai.help.tools' => CountVisitors::class]);
        Helper::fake(['Seven.']);

        $this->answer();

        Helper::assertPrompted(fn (AgentPrompt $prompt): bool => [...$prompt->agent->tools()][5] instanceof CountVisitors);
    }

    public function test_a_class_that_is_not_a_tool_is_left_out(): void
    {
        config(['atrium-ai.help.tools' => [\stdClass::class, CountVisitors::class]]);
        Helper::fake(['Seven.']);

        $this->answer();

        Helper::assertPrompted(function (AgentPrompt $prompt): bool {
            $tools = [...$prompt->agent->tools()];

            return count($tools) === 6 && $tools[5] instanceof CountVisitors;
        });
    }

    /**
     * Runs the job the way a worker does and reads what the chat would.
     *
     * @param  list<array{role:string,text:string}>  $history
     * @return array<string,mixed>
     */
    private function answer(string $question = 'How many?', array $history = [], string $locale = 'en', ?int $site = null, ?int $user = null): array
    {
        $user ??= $this->admin()->id;
        $id = app(Results::class)->start($user);

        app()->call([new Answer($id, $user, $question, $history, $locale, $site ?? $this->mainSite()->id), 'handle']);

        return (array) app(Results::class)->find($id, $user);
    }
}
