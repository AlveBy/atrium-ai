<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Http\Controllers;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Ai\Brief;
use Atrium\Ai\Briefs;
use Atrium\Ai\Jobs\Generate;
use Atrium\Ai\Results;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The "Generate" button of the admin form: starts a job and answers with its
 * id; the page then asks for the result until it is there. With the "sync"
 * queue the result comes with the first answer.
 */
final class GenerateController extends Controller
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly Briefs $briefs,
        private readonly Results $results,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'max:100'],
            'field' => ['required', 'string', 'max:100'],
            'values' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', 'max:20'],
            'translate' => ['nullable', 'boolean'],
        ]);

        $user = $request->user(config('atrium.auth.guard', 'admin'));
        $model = $this->registry->model($data['module']);

        abort_if($model === null, 404);
        abort_unless(self::mayWrite($user, $data['module']), 403, __('atrium::permissions.forbidden'));

        $owner = $user instanceof AdminUser ? (int) $user->getKey() : null;
        $brief = $this->briefs->make(
            $model,
            $data['field'],
            $data['values'] ?? [],
            $data['locale'] ?? null,
            (bool) ($data['translate'] ?? false),
            $data['module'],
            $owner,
        );

        $id = $this->results->start($owner);
        $this->dispatch($id, $brief);

        return response()->json(['id' => $id, ...($this->results->find($id, $owner) ?? ['status' => 'pending'])], 202);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user(config('atrium.auth.guard', 'admin'));
        $entry = $this->results->find($id, $user instanceof AdminUser ? (int) $user->getKey() : null);

        abort_if($entry === null, 404);

        return response()->json(['id' => $id, ...$entry]);
    }

    /**
     * Generating is part of editing: whoever may create or edit records of
     * the module may ask.
     */
    public static function mayWrite(mixed $user, string $module): bool
    {
        return $user instanceof AdminUser
            && ($user->hasAdminPermission("{$module}.update") || $user->hasAdminPermission("{$module}.create"));
    }

    /**
     * Without a queue connection of its own (the default) the job runs in
     * this PHP process once the answer has gone to the browser: no worker
     * needed, and the form still does not wait. With one, a worker runs it.
     */
    private function dispatch(string $id, Brief $brief): void
    {
        $job = new Generate($id, $brief);
        $connection = config('atrium-ai.queue.connection');

        if ($connection === null || $connection === '') {
            app()->terminating(static function () use ($job): void {
                // A host may forbid it: the warning is silenced.
                @set_time_limit($job->timeout);

                app()->call([$job, 'handle']);
            });

            return;
        }

        $job->onConnection((string) $connection);

        if (($queue = config('atrium-ai.queue.queue')) !== null && $queue !== '') {
            $job->onQueue((string) $queue);
        }

        dispatch($job);
    }
}
