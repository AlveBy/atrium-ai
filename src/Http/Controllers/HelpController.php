<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Http\Controllers;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\Sites;
use Atrium\Ai\Jobs\Answer;
use Atrium\Ai\Jobs\Background;
use Atrium\Ai\Providers;
use Atrium\Ai\Results;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * A question to the helper in the corner of the admin. The answer is polled
 * at the same address as a generated text (admin.ai.result).
 */
final class HelpController extends Controller
{
    public function __invoke(Request $request, Results $results, Providers $providers): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.text' => ['required', 'string', 'max:6000'],
        ]);

        $user = $request->user(config('atrium.auth.guard', 'admin'));

        abort_unless($user instanceof AdminUser, 403);
        abort_unless($providers->available('text') && config('atrium-ai.help.enabled', true), 404);

        $owner = $user->getKey();
        $id = $results->start($owner);

        Background::run(new Answer(
            $id,
            $owner,
            $data['question'],
            // Only the role and the text of a turn pass the validation.
            array_values($data['history'] ?? []),
            app()->getLocale(),
            app(Sites::class)->current()->id,
        ));

        return response()->json(['id' => $id, ...($results->find($id, $owner) ?? ['status' => 'pending'])], 202);
    }
}
