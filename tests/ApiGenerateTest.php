<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\AiServiceProvider;
use Atrium\Ai\Mcp\GenerateText;
use Atrium\Ai\Tests\Concerns\CallsTheApi;
use Atrium\Ai\Tests\Fixtures\Article;
use Atrium\Ai\Tests\Fixtures\Plain;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * POST /api/v1/ai/generate and the generate_text tool: what is checked
 * before a provider is asked, and what is read from the record.
 */
final class ApiGenerateTest extends TestCase
{
    use CallsTheApi;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Article::class, Plain::class]);
    }

    private function token(): string
    {
        return $this->tokenOf(['articles.view', 'articles.update', 'plain.update'], ['articles:read', 'articles:write', 'plain:write']);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function invalid(): array
    {
        $ok = ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']];

        return [
            'no module' => [['module' => null] + $ok, 'module'],
            'no field' => [['field' => null] + $ok, 'field'],
            'a module left out' => [array_diff_key($ok, ['module' => 1]), 'module'],
            'a field left out' => [array_diff_key($ok, ['field' => 1]), 'field'],
            'a module that is not a text' => [['module' => ['articles']] + $ok, 'module'],
            'an id that is not a number' => [['id' => 'last'] + $ok, 'id'],
            'values that are not a list' => [['values' => 'Lake'] + $ok, 'values'],
            'a language code too long' => [['locale' => str_repeat('x', 21)] + $ok, 'locale'],
            'a locale that is not a text' => [['locale' => ['en']] + $ok, 'locale'],
            'a translate that is not a flag' => [['translate' => 'maybe'] + $ok, 'translate'],
            'an improve that is not a flag' => [['improve' => 'maybe'] + $ok, 'improve'],
            'a selection that is not a text' => [['selection' => ['a']] + $ok, 'selection'],
            'a field name too long' => [['field' => str_repeat('x', 101)] + $ok, 'field'],
            'a module name too long' => [['module' => str_repeat('x', 101)] + $ok, 'module'],
        ];
    }

    /**
     * @param  array<string,mixed>  $body
     */
    #[DataProvider('invalid')]
    public function test_a_request_that_is_not_in_order_is_refused_before_anything_is_asked(array $body, string $field): void
    {
        Writer::fake()->preventStrayPrompts();

        $this->withToken($this->token())->postJson('/api/v1/ai/generate', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        Writer::assertNeverPrompted();
    }

    public function test_what_is_left_out_may_be_null(): void
    {
        Writer::fake(['Text', 'Text']);
        $article = Article::query()->create(['name' => ['en' => 'Lake']]);

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'id' => null, 'values' => ['name' => 'Lake'], 'locale' => null, 'translate' => null])
            ->assertOk()
            ->assertJsonPath('data.value', 'Text');

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'id' => $article->id, 'values' => null])
            ->assertOk()
            ->assertJsonPath('data.value', 'Text');
    }

    public function test_an_unknown_module_is_told_where_the_list_is(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'recipes', 'field' => 'detail_text'])
            ->assertNotFound()
            ->assertJsonPath('message', 'No such module. See the list in the documentation: /api/v1');

        config(['atrium.api.prefix' => 'api/v2']);

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'recipes', 'field' => 'detail_text'])
            ->assertJsonPath('message', 'No such module. See the list in the documentation: /api/v2');
    }

    public function test_a_token_without_the_ability_or_the_right_is_told_which_one_it_lacks(): void
    {
        $body = ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']];

        $this->withToken($this->tokenOf(['articles.update'], ['articles:read']))->postJson('/api/v1/ai/generate', $body)
            ->assertForbidden()->assertJsonPath('message', 'The token lacks the articles:write ability.');

        $this->withToken($this->tokenOf(['articles.view'], ['articles:write']))->postJson('/api/v1/ai/generate', $body)
            ->assertForbidden()->assertJsonPath('message', 'The token owner lacks the articles.update permission.');
    }

    public function test_the_translations_of_a_record_are_read_in_every_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        $article = Article::query()->create(['name' => ['en' => 'Lake', 'de' => 'See']]);
        Writer::fake(['Text']);

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'id' => $article->id, 'locale' => 'de'])
            ->assertOk();

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Name: See'));
    }

    public function test_the_values_sent_win_over_the_saved_ones(): void
    {
        $article = Article::query()->create(['name' => ['en' => 'Lake']]);
        Writer::fake(['Text']);

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'id' => $article->id, 'values' => ['name' => ['en' => 'Sea']]])
            ->assertOk();

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Name: Sea') && ! str_contains($prompt->prompt, 'Lake'));
    }

    public function test_a_record_that_is_not_there_is_a_404(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'id' => 999])
            ->assertNotFound();
    }

    public function test_a_model_that_is_not_translatable_is_read_as_it_is(): void
    {
        $plain = Plain::query()->create(['name' => ['en' => 'Lake']]);
        Writer::fake(['Text']);

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'plain', 'field' => 'detail_text', 'id' => $plain->id])
            ->assertOk()
            ->assertJsonPath('data.value', 'Text');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Name: Lake'));
    }

    public function test_a_translation_is_asked_for_by_the_flag(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Hallo']);

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'preview_text', 'locale' => 'en', 'translate' => true, 'values' => ['preview_text' => ['en' => 'Hello']]])
            ->assertOk()
            ->assertJsonPath('data.values.de', 'Hallo');
    }

    public function test_a_text_is_improved_from_the_saved_record(): void
    {
        $article = Article::query()->create(['name' => ['en' => 'Lake'], 'preview_text' => ['en' => 'Roms by the lake']]);
        Writer::fake(['Rooms by the lake']);

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'preview_text', 'id' => $article->id, 'improve' => true])
            ->assertOk()
            ->assertJsonPath('data.value', 'Rooms by the lake');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Text:\nRoms by the lake"));
        $this->assertSame(['en' => 'Roms by the lake'], $article->fresh()->getTranslations('preview_text'));
    }

    public function test_a_piece_of_the_text_is_improved_through_the_tool(): void
    {
        Writer::fake(['by the lake']);

        $result = $this->mcp($this->token(), 'tools/call', ['name' => 'generate_text', 'arguments' => [
            'module' => 'articles', 'field' => 'detail_text', 'improve' => true, 'selection' => 'bye the lake', 'values' => ['detail_text' => '<p>Rooms bye the lake</p>'],
        ]])->json('result');

        $this->assertFalse($result['isError'], (string) ($result['content'][0]['text'] ?? ''));
        $this->assertSame('by the lake', json_decode($result['content'][0]['text'], true)['data']['value']);
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Text:\nbye the lake") && ! str_contains($prompt->prompt, 'Rooms'));
    }

    public function test_an_empty_field_is_told_there_is_nothing_to_improve(): void
    {
        Writer::fake(['Text'])->preventStrayPrompts();

        $this->withToken($this->token())
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'improve' => true, 'values' => ['detail_text' => ' ']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.no_source'));

        Writer::assertNeverPrompted();
    }

    public function test_the_generate_text_tool_passes_the_language_on(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Text']);

        $result = $this->mcp($this->token(), 'tools/call', ['name' => 'generate_text', 'arguments' => [
            'module' => 'articles', 'field' => 'detail_text', 'locale' => 'de', 'values' => ['name' => ['en' => 'Lake', 'de' => 'See']],
        ]])->json('result');

        $this->assertFalse($result['isError'], (string) ($result['content'][0]['text'] ?? ''));
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Name: See'));
    }

    public function test_the_generate_text_tool_takes_every_argument_of_its_schema(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        $article = Article::query()->create(['name' => ['en' => 'Lake'], 'preview_text' => ['en' => 'Hello']]);
        Writer::fake(['Hallo']);
        $token = $this->token();

        $tool = collect($this->mcp($token, 'tools/list')->json('result.tools'))->firstWhere('name', 'generate_text');
        $this->assertEqualsCanonicalizing(['module', 'site', 'field', 'id', 'values', 'locale', 'translate', 'improve', 'selection'], array_keys($tool['inputSchema']['properties']));
        $this->assertContains('module', $tool['inputSchema']['required']);
        $this->assertContains('field', $tool['inputSchema']['required']);

        $result = $this->mcp($token, 'tools/call', ['name' => 'generate_text', 'arguments' => [
            'module' => 'articles', 'field' => 'preview_text', 'id' => $article->id, 'locale' => 'en', 'translate' => true, 'values' => ['name' => ['en' => 'Sea']],
        ]])->json('result');

        $this->assertFalse($result['isError'], (string) ($result['content'][0]['text'] ?? ''));
        $this->assertSame(['de' => 'Hallo'], json_decode($result['content'][0]['text'], true)['data']['values']);
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Hello'));
    }

    public function test_the_tool_is_offered_once_after_config_cache(): void
    {
        // A cached config already holds the tool: booting on top of it must not add it again.
        $this->app->getProvider(AiServiceProvider::class)->boot();

        $this->assertSame(1, array_count_values((array) config('atrium.mcp.tools'))[GenerateText::class]);
    }
}
