<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Exceptions\NotConfigured;
use Atrium\Ai\Models\Usage;
use Carbon\CarbonInterface;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\AgentResponse;
use Throwable;

/**
 * Turns a brief into text: renders the task's prompt, asks the provider
 * chain (the SDK moves on to the next provider on a limit or a timeout),
 * cleans the answer and logs the call without its text.
 */
final class Assistant
{
    public function __construct(
        private readonly Providers $providers,
        private readonly ContentLanguages $languages,
    ) {}

    public function generate(Brief $brief): Result
    {
        if ($brief->task === Task::Translate) {
            $values = [];
            $response = null;

            foreach ($brief->targets as $code => $name) {
                [$values[$code], $response] = $this->ask($brief, $this->prompt($brief, ['target' => $name, 'target_code' => $code]), $code);
            }

            return new Result(values: $values, provider: $response?->meta->provider, model: $response?->meta->model);
        }

        $attachments = $brief->image !== null ? [Image::fromPath($brief->image)] : [];
        [$value, $response] = $this->ask($brief, $this->prompt($brief), $brief->language, $attachments);

        return new Result(value: $value, provider: $response->meta->provider, model: $response->meta->model);
    }

    /**
     * Vectors of texts with the "embeddings" chain, for modules that search
     * by meaning.
     *
     * @param  array<int,string>  $texts
     * @return array<int,array<int,float>>
     */
    public function embed(array $texts): array
    {
        $chain = $this->providers->chain('embeddings');

        if ($chain === []) {
            throw NotConfigured::for('embeddings');
        }

        return Embeddings::for($texts)->generate(provider: $chain)->embeddings;
    }

    /**
     * @param  array<int,mixed>  $attachments
     * @return array{0:string,1:AgentResponse}
     */
    private function ask(Brief $brief, string $prompt, string $language, array $attachments = []): array
    {
        $chain = $this->providers->chain($brief->task->kind());

        if ($chain === []) {
            throw NotConfigured::for($brief->task->kind());
        }

        $started = now();

        try {
            $response = (new Writer($this->system($language)))->prompt(
                $prompt,
                $attachments,
                provider: $chain,
                timeout: (int) config('atrium-ai.timeout', 60),
            );
        } catch (Throwable $e) {
            $this->log($brief, $started, null);

            throw $e;
        }

        $this->log($brief, $started, $response);

        return [Output::clean($response->text, $brief->format, $brief->max), $response];
    }

    /**
     * @param  array<string,mixed>  $extra
     */
    private function prompt(Brief $brief, array $extra = []): string
    {
        // The shorter of what the task asks for and what the field allows.
        $limits = array_filter([$brief->task->limit(), $brief->max], static fn (?int $limit): bool => $limit !== null);

        return trim(view($this->view($brief->task->value), [
            'task' => $brief->task,
            'field' => $brief->field,
            'sources' => $brief->sources,
            'text' => implode("\n\n", $brief->sources),
            'language' => $this->languageName($brief->language),
            'html' => $brief->format === 'html',
            'limit' => $limits === [] ? null : min($limits),
            ...$extra,
        ])->render());
    }

    private function system(string $language): string
    {
        return trim(view($this->view('system'), [
            'site' => app(Sites::class)->current()->name,
            'language' => $this->languageName($language),
        ])->render());
    }

    private function view(string $key): string
    {
        return (string) config("atrium-ai.prompts.{$key}", "atrium-ai::prompts.{$key}");
    }

    private function languageName(string $code): string
    {
        return LanguageNames::describe($code, $this->languages->find($code)?->name);
    }

    private function log(Brief $brief, CarbonInterface $started, ?AgentResponse $response): void
    {
        if (! config('atrium-ai.log', true)) {
            return;
        }

        try {
            Usage::query()->create([
                'task' => $brief->task->value,
                'module' => $brief->module,
                'admin_user_id' => $brief->user,
                'provider' => $response?->meta->provider,
                'model' => $response?->meta->model,
                'input_tokens' => $response->usage->inputTokens ?? 0,
                'output_tokens' => $response->usage->outputTokens ?? 0,
                'duration_ms' => round($started->diffInMilliseconds(now())),
                'succeeded' => $response !== null,
            ]);
        } catch (Throwable $e) {
            // The log must never cost the editor the answer.
            report($e);
        }
    }
}
