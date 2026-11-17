<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Tests\Concerns\CallsTheApi;
use Atrium\Ai\Tests\Fixtures\Article;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;

/**
 * The same generation for external programs: the API and the MCP server,
 * with the API's tokens and the API's rules.
 */
final class ApiTest extends TestCase
{
    use CallsTheApi;

    public function test_the_api_writes_from_the_stored_record_and_saves_nothing(): void
    {
        $article = Article::query()->create(['name' => ['en' => 'Lake Hotel'], 'detail_text' => '<p>Rooms by the water, a sauna and a pier.</p>']);
        Writer::fake(['Rooms by the water with a sauna.']);

        $this->withToken($this->tokenOf(['articles.view', 'articles.update'], ['articles:read', 'articles:write']))
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'preview_text', 'id' => $article->id])
            ->assertOk()
            ->assertJsonPath('data.value', 'Rooms by the water with a sauna.')
            ->assertJsonPath('data.provider', 'gemini');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'a sauna and a pier'));
        $this->assertNull($article->fresh()->getTranslation('preview_text', 'en', false) ?: null);
    }

    public function test_the_api_takes_the_write_ability_and_the_owners_right(): void
    {
        Writer::fake(['Text']);
        $body = ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake Hotel']];

        $this->postJson('/api/v1/ai/generate', $body)->assertUnauthorized();

        $this->withToken($this->tokenOf(['articles.view', 'articles.update'], ['articles:read']))
            ->postJson('/api/v1/ai/generate', $body)->assertForbidden();

        $this->withToken($this->tokenOf(['articles.view'], ['articles:read', 'articles:write']))
            ->postJson('/api/v1/ai/generate', $body)->assertForbidden();
    }

    public function test_an_assistant_gets_a_generate_text_tool(): void
    {
        Writer::fake(['A cosy hotel by the lake.']);
        $token = $this->tokenOf(['articles.view', 'articles.update'], ['articles:read', 'articles:write']);

        $names = array_column($this->mcp($token, 'tools/list')->json('result.tools'), 'name');
        $this->assertContains('generate_text', $names);
        $this->assertContains('list_modules', $names);

        $result = $this->mcp($token, 'tools/call', ['name' => 'generate_text', 'arguments' => [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ]])->json('result');

        $this->assertFalse($result['isError'], (string) ($result['content'][0]['text'] ?? ''));
        $this->assertSame('A cosy hotel by the lake.', json_decode($result['content'][0]['text'], true)['data']['value']);
    }

    public function test_a_provider_that_fails_answers_503_with_a_plain_message(): void
    {
        Exceptions::fake();
        Writer::fake(fn () => throw new RuntimeException('Connection timed out after 60 seconds'));

        $this->withToken($this->tokenOf(['articles.view', 'articles.update'], ['articles:read', 'articles:write']))
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake Hotel']])
            ->assertStatus(503)
            ->assertExactJson(['message' => __('atrium-ai::ai.errors.failed')]);

        // What the provider said stays in the log: it may quote the request.
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_a_provider_without_a_key_answers_503_and_says_so(): void
    {
        Writer::fake()->preventStrayPrompts();
        config(['ai.providers.gemini.key' => null, 'ai.providers.groq.key' => null]);

        $this->withToken($this->tokenOf(['articles.view', 'articles.update'], ['articles:read', 'articles:write']))
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake Hotel']])
            ->assertStatus(503)
            ->assertExactJson(['message' => __('atrium-ai::ai.errors.not_configured')]);
    }

    public function test_the_generate_text_tool_names_a_module_it_does_not_know(): void
    {
        Writer::fake()->preventStrayPrompts();
        $token = $this->tokenOf(['articles.view', 'articles.update'], ['articles:read', 'articles:write']);

        $result = $this->mcp($token, 'tools/call', ['name' => 'generate_text', 'arguments' => [
            'module' => 'recipes',
            'field' => 'detail_text',
        ]])->json('result');

        $this->assertTrue($result['isError']);
        $this->assertSame('No module "recipes". See list_modules.', $result['content'][0]['text']);
        Writer::assertNeverPrompted();
    }
}
