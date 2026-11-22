<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Atrium AI
|--------------------------------------------------------------------------
|
| The "Generate" buttons of the admin, on top of the Laravel AI SDK
| (laravel/ai). Providers and their keys live in the SDK's config/ai.php and
| are read from .env (GEMINI_API_KEY, GROQ_API_KEY, OPENROUTER_API_KEY,
| OLLAMA_URL…). Nothing here holds a key.
|
*/

return [

    // Switch the buttons off without removing the keys.
    'enabled' => (bool) env('ATRIUM_AI_ENABLED', true),

    /*
    | Which provider answers, per kind of work, in order: when one answers
    | with a rate limit, an overload, a timeout or a refusal of the region (a
    | 403, "location is not supported"), the next one is asked. A provider may
    | be listed with several models: they are tried one after the other.
    | "provider" or "provider:model"; without a model the SDK's default for
    | that provider is used. A provider without a key in config/ai.php is
    | skipped (Ollama needs none: list it and it is used). No provider left
    | for a kind = its buttons are not shown.
    |
    | The defaults are free tiers: Gemini (Google AI Studio key), Groq,
    | OpenRouter's free models. Gemini's free tier lets Google use the prompts
    | to improve its products: for private data use Ollama or a paid key.
    */
    'chains' => [
        'text' => env('ATRIUM_AI_TEXT', 'gemini,groq,openrouter:meta-llama/llama-3.3-70b-instruct:free'),
        'vision' => env('ATRIUM_AI_VISION', 'gemini,openrouter:google/gemma-3-27b-it:free'),
        'embeddings' => env('ATRIUM_AI_EMBEDDINGS', 'gemini'),
    ],

    // A provider that refuses the region (a 403, or "location is not
    // supported") is left out of the chains for this many seconds.
    'refused_for' => 3600,

    // Seconds one provider may take before the next one is asked.
    'timeout' => (int) env('ATRIUM_AI_TIMEOUT', 60),

    /*
    | The admin starts a generation as a job and polls for the result, so the
    | form never waits on a provider. No connection (the default): the job
    | runs in the same PHP process right after the answer is sent, so no
    | queue worker is needed. A connection ("database", "redis"): a worker
    | runs it (php artisan queue:work). "sync": inside the request itself.
    */
    'queue' => [
        'connection' => env('ATRIUM_AI_QUEUE_CONNECTION'),
        'queue' => env('ATRIUM_AI_QUEUE'),
    ],

    // How long a finished result waits for the editor, in seconds.
    'result_ttl' => 600,

    /*
    | The prompt of each task: a Blade view. Publish the package's views
    | (php artisan vendor:publish --tag=atrium-ai-prompts) and edit them, or
    | point a task at a view of your own here. "system" is the instruction
    | every task starts with.
    */
    'prompts' => [
        'system' => 'atrium-ai::prompts.system',
        'describe' => 'atrium-ai::prompts.describe',
        'preview' => 'atrium-ai::prompts.preview',
        'seo_title' => 'atrium-ai::prompts.seo-title',
        'seo_description' => 'atrium-ai::prompts.seo-description',
        'seo_keywords' => 'atrium-ai::prompts.seo-keywords',
        'alt' => 'atrium-ai::prompts.alt',
        'translate' => 'atrium-ai::prompts.translate',
        'help' => 'atrium-ai::prompts.help',
    ],

    /*
    | The helper in the corner of the admin: answers from the guides and reads
    | the records of the modules the admin may view (never writes). What it
    | reads goes to the provider, like any prompt.
    | "docs": folders of *.md guides and globs of module READMEs.
    | "tools": classes of your own Laravel AI SDK tools, made with the
    | admin ($user) and the interface language ($locale).
    */
    'help' => [
        'enabled' => (bool) env('ATRIUM_AI_HELP', true),
        'docs' => [
            base_path('docs'),
            base_path('vendor/alveby/*/README.md'),
            base_path('packages/*/README.md'),
        ],
        'tools' => [],
    ],

    /*
    | In a public demo (ATRIUM_DEMO=true) all visitors are one administrator,
    | so the per-minute throttle is shared and no one stops a single visitor
    | from using the provider's whole free quota. While the demo mode is on,
    | the site as a whole gets "daily_limit" provider calls a day (a text, a
    | helper question, or a call per language of a translation), counted from
    | midnight in the app's time zone; then the dialogs say the day's limit is
    | used up and the API and MCP answer 429, without asking a provider.
    | The default is below the 50 requests a day that an OpenRouter key with
    | no credit gets for all its free models. 0 = no ceiling. Outside demo
    | mode nothing is counted.
    | "store": the cache store of the counter. Not "database": the demo's
    | hourly reset empties it and gives the visitors a new allowance.
    */
    'demo' => [
        'daily_limit' => (int) env('ATRIUM_AI_DEMO_DAILY_LIMIT', 40),
        'store' => env('ATRIUM_AI_DEMO_STORE', 'file'),
    ],

    // How much of each source field goes into a prompt, in characters.
    'max_source_length' => 6000,

    // Every call is written to the ai_usage table: task, provider, model,
    // tokens, duration. Never the texts.
    'log' => (bool) env('ATRIUM_AI_LOG', true),
];
