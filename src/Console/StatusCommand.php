<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Console;

use Atrium\Ai\Models\Usage;
use Atrium\Ai\Providers;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

/**
 * Which providers answer and what they have cost lately.
 */
final class StatusCommand extends Command
{
    protected $signature = 'atrium:ai:status {--days=30 : The period of the usage summary}';

    protected $description = 'Show the AI provider chains and the usage of the last days';

    public function handle(Providers $providers): int
    {
        $rows = [];

        foreach (Providers::KINDS as $kind) {
            foreach ($providers->describe($kind) as $entry) {
                $rows[] = [$kind, $entry['provider'], $entry['model'] ?? '(default)', $entry['usable'] ? 'yes' : 'no key'];
            }
        }

        $this->table(['Kind', 'Provider', 'Model', 'Used'], $rows);

        if (! $providers->available('text')) {
            $this->warn('No text provider has a key: the "Generate" buttons are hidden. Set GEMINI_API_KEY, GROQ_API_KEY, OPENROUTER_API_KEY or list ollama in ATRIUM_AI_TEXT.');
        }

        try {
            $usage = Usage::query()->toBase()
                ->where('created_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
                ->selectRaw('provider, count(*) as calls, sum(case when succeeded then 0 else 1 end) as failed, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, avg(duration_ms) as duration')
                ->groupBy('provider')
                ->orderBy('provider')
                ->get();
        } catch (QueryException) {
            $this->line('No usage log yet: run php artisan atrium:module:install alveby/atrium-ai.');

            return self::SUCCESS;
        }

        $this->table(
            ['Provider', 'Calls', 'Failed', 'Input tokens', 'Output tokens', 'Avg ms'],
            $usage->map(fn ($row): array => [
                $row->provider ?? '(none answered)',
                (int) $row->calls,
                (int) $row->failed,
                (int) $row->input_tokens,
                (int) $row->output_tokens,
                (int) round((float) $row->duration),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
