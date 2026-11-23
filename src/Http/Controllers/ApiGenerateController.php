<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Http\Controllers;

use Atrium\Admin\Api\ApiResources;
use Atrium\Ai\Assistant;
use Atrium\Ai\Briefs;
use Atrium\Ai\DemoLimit;
use Atrium\Ai\Failures;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * POST /api/v1/ai/generate — the same "Generate" for programs and AI
 * assistants (the MCP tool generate_text calls it). Answers right away with
 * the text; nothing is saved: send it back with PUT when it is good.
 */
final class ApiGenerateController extends Controller
{
    public function __construct(
        private readonly ApiResources $resources,
        private readonly Briefs $briefs,
        private readonly Assistant $assistant,
        private readonly DemoLimit $limit,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'max:100'],
            'field' => ['required', 'string', 'max:100'],
            'id' => ['nullable', 'integer'],
            'values' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', 'max:20'],
            'translate' => ['nullable', 'boolean'],
            'improve' => ['nullable', 'boolean'],
            'selection' => ['nullable', 'string', 'max:100000'],
        ]);

        $module = $data['module'];
        $model = $this->resources->model($module);
        $owner = $request->user();

        abort_if($model === null, 404, 'No such module. See the list in the documentation: /'.config('atrium.api.prefix', 'api/v1'));
        abort_unless($owner->tokenCan("{$module}:write"), 403, "The token lacks the {$module}:write ability.");
        abort_unless(GenerateController::mayWrite($owner, $module), 403, "The token owner lacks the {$module}.update permission.");

        $values = [...$this->stored($model, $data['id'] ?? null), ...($data['values'] ?? [])];
        $brief = $this->briefs->make($model, $data['field'], $values, $data['locale'] ?? null, (bool) ($data['translate'] ?? false), $module, (int) $owner->getKey(), (bool) ($data['improve'] ?? false), $data['selection'] ?? null);

        $this->limit->claim($brief->calls());

        try {
            return response()->json(['data' => $this->assistant->generate($brief)->toArray()]);
        } catch (Throwable $e) {
            return response()->json(['message' => Failures::message($e)], 503);
        }
    }

    /**
     * The saved values of a record; a translatable one is already every language.
     *
     * @param  class-string<Model>  $model
     * @return array<string,mixed>
     */
    private function stored(string $model, ?int $id): array
    {
        if ($id === null) {
            return [];
        }

        return $model::query()->findOrFail($id)->attributesToArray();
    }
}
