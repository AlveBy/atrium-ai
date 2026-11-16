<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Atrium\Ai\Http\Controllers\ApiGenerateController;
use Atrium\Ai\Http\Controllers\GenerateController;
use Illuminate\Support\Facades\Route;

// The admin form's "Generate" button: start, then poll.
Route::prefix(config('atrium.prefix', 'admin'))
    ->middleware([...config('atrium.middleware', ['web']), 'atrium.site', 'atrium.inertia', 'admin.auth', 'throttle:30,1'])
    ->group(function (): void {
        Route::post('ai/generate', [GenerateController::class, 'store'])->name('admin.ai.generate');
        Route::get('ai/generate/{id}', [GenerateController::class, 'show'])
            ->whereUuid('id')
            ->withoutMiddleware('throttle:30,1')
            ->name('admin.ai.result');
    });

// The same for API tokens (and the MCP tool generate_text).
Route::prefix(config('atrium.api.prefix', 'api/v1'))
    ->middleware(['api', 'atrium.site', 'admin.api', 'throttle:30,1'])
    ->group(function (): void {
        Route::post('ai/generate', ApiGenerateController::class)->name('admin.api.ai.generate');
    });
