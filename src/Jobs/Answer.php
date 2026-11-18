<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Jobs;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Ai\Agents\Helper;
use Atrium\Ai\Failures;
use Atrium\Ai\Help\Records;
use Atrium\Ai\Help\Tools\DescribeModule;
use Atrium\Ai\Help\Tools\GetRecord;
use Atrium\Ai\Help\Tools\ListModules;
use Atrium\Ai\Help\Tools\ListRecords;
use Atrium\Ai\Help\Tools\SearchDocs;
use Atrium\Ai\LanguageNames;
use Atrium\Ai\Models\Usage;
use Atrium\Ai\Providers;
use Atrium\Ai\Result;
use Atrium\Ai\Results;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

/**
 * One question to the helper, off the request: the chat polls for the answer.
 */
final class Answer implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    /** A few tool calls, each a round trip to the provider. */
    public int $timeout = 300;

    /**
     * @param  list<array{role:string,text:string}>  $history  earlier questions and answers
     * @param  int  $site  the site the admin was working on: a queue worker has none of its own
     */
    public function __construct(
        public readonly string $id,
        public readonly int $user,
        public readonly string $question,
        public readonly array $history,
        public readonly string $locale,
        public readonly int $site,
    ) {}

    public function handle(Results $results, Providers $providers): void
    {
        $started = now();
        $response = null;
        $previous = app()->getLocale();
        app()->setLocale($this->locale);
        $sites = app(Sites::class);
        $previousSite = $sites->isResolved() ? $sites->current() : null;

        try {
            // Records of the other sites stay hidden, as in the admin.
            $sites->setCurrent(Site::query()->findOrFail($this->site));
            $user = AdminUser::query()->findOrFail($this->user);
            $response = (new Helper($this->instructions($user), $this->tools($user), $this->messages()))->prompt(
                $this->question,
                provider: $providers->chain('text'),
                timeout: (int) config('atrium-ai.timeout', 60),
            );

            $results->done($this->id, new Result(value: trim(mb_substr($response->text, 0, 6000)), provider: $response->meta->provider, model: $response->meta->model));
        } catch (Throwable $e) {
            $results->failed($this->id, Failures::message($e));
        }

        app()->setLocale($previous);
        $previousSite !== null ? $sites->setCurrent($previousSite) : $sites->forgetCurrent();
        $this->log($started, $response);
    }

    /**
     * The helper's own tools, then those other packages add
     * (config atrium-ai.help.tools): each is made with the admin and the locale.
     *
     * @return list<Tool>
     */
    private function tools(AdminUser $user): array
    {
        $tools = [];

        foreach ([ListModules::class, DescribeModule::class, ListRecords::class, GetRecord::class, SearchDocs::class, ...(array) config('atrium-ai.help.tools', [])] as $class) {
            $tool = app()->make($class, ['user' => $user, 'locale' => $this->locale]);

            if ($tool instanceof Tool) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    private function instructions(AdminUser $user): string
    {
        return trim(view((string) config('atrium-ai.prompts.help', 'atrium-ai::prompts.help'), [
            'site' => app(Sites::class)->current()->name,
            'language' => LanguageNames::describe($this->locale, config("atrium.locales.{$this->locale}")),
            'modules' => app(Records::class)->modules($user),
        ])->render());
    }

    /**
     * @return list<UserMessage|AssistantMessage>
     */
    private function messages(): array
    {
        return array_map(
            static fn (array $turn) => $turn['role'] === 'assistant' ? new AssistantMessage($turn['text']) : new UserMessage($turn['text']),
            $this->history,
        );
    }

    private function log(CarbonInterface $started, ?AgentResponse $response): void
    {
        if (! config('atrium-ai.log', true)) {
            return;
        }

        try {
            Usage::query()->create([
                'task' => 'help',
                'admin_user_id' => $this->user,
                'provider' => $response?->meta->provider,
                'model' => $response?->meta->model,
                'input_tokens' => $response->usage->inputTokens ?? 0,
                'output_tokens' => $response->usage->outputTokens ?? 0,
                'duration_ms' => round($started->diffInMilliseconds(now())),
                'succeeded' => $response !== null,
            ]);
        } catch (Throwable $e) {
            // The log must never cost the admin the answer.
            report($e);
        }
    }
}
