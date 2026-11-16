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
    | with a rate limit, an overload or a timeout, the next one is asked.
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
    ],

    // How much of each source field goes into a prompt, in characters.
    'max_source_length' => 6000,

    // Every call is written to the ai_usage table: task, provider, model,
    // tokens, duration. Never the texts.
    'log' => (bool) env('ATRIUM_AI_LOG', true),
];
