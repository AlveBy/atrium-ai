<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Modules;
use Atrium\Ai\Console\StatusCommand;
use Atrium\Ai\Mcp\GenerateText;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

/**
 * The migration comes from the manifest in composer.json (`extra.atrium`)
 * once `atrium:module:install alveby/atrium-ai` has run; this adds the
 * routes, the "Generate" buttons and the MCP tool.
 */
final class AiServiceProvider extends ServiceProvider
{
    public const MODULE = 'alveby/atrium-ai';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/atrium-ai.php', 'atrium-ai');
        $this->app->singleton(Providers::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium-ai');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atrium-ai');

        if ($this->app->runningInConsole()) {
            $this->commands([StatusCommand::class]);

            $this->publishes([
                __DIR__.'/../config/atrium-ai.php' => config_path('atrium-ai.php'),
            ], 'atrium-ai-config');

            $this->publishes([
                __DIR__.'/../resources/views/prompts' => resource_path('views/vendor/atrium-ai/prompts'),
            ], 'atrium-ai-prompts');
        }

        // Required but not installed: no table for the log, so no buttons.
        if (! $this->app->make(Modules::class)->isInstalled(self::MODULE)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/ai.php');
        config()->push('atrium.mcp.tools', GenerateText::class);

        // Lazy: runs per page, after the admin is signed in.
        Inertia::share('ai', fn () => $this->client());
    }

    /**
     * What the admin's pages need to draw the buttons; null (no buttons)
     * without a provider that has a key.
     *
     * @return array<string,mixed>|null
     */
    private function client(): ?array
    {
        $providers = $this->app->make(Providers::class);
        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

        if (! $user instanceof AdminUser || ! $providers->available('text')) {
            return null;
        }

        $tasks = array_values(array_map(
            static fn (Task $task): string => $task->value,
            array_filter(Task::cases(), static fn (Task $task): bool => $providers->available($task->kind())),
        ));

        return [
            'url' => route('admin.ai.generate', [], false),
            'tasks' => $tasks,
            'labels' => trans('atrium-ai::ai.ui'),
        ];
    }
}
