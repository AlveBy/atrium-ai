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
use Atrium\Ai\Models\Usage;
use Atrium\Ai\Providers;
use Atrium\Ai\Tests\Fixtures\Article;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * The "Generate" button of the admin form, with the SDK's fake provider.
 */
final class GenerateTest extends TestCase
{
    public function test_a_description_is_written_from_the_name_and_offered_not_saved(): void
    {
        Writer::fake(['A cosy hotel by the lake.']);

        $response = $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => ['en' => 'Lake Hotel'], 'code' => 'lake-hotel', 'preview_text' => ['en' => 'Rooms <b>by</b> the water']],
        ]);

        // The sync queue has finished by the time the answer comes.
        $response->assertStatus(202)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('value', 'A cosy hotel by the lake.')
            ->assertJsonPath('provider', 'gemini');

        $this->getJson('/admin/ai/generate/'.$response->json('id'))
            ->assertOk()
            ->assertJsonPath('value', 'A cosy hotel by the lake.');

        Writer::assertPrompted(function (AgentPrompt $prompt): bool {
            return str_contains($prompt->prompt, 'Name: Lake Hotel')
                && str_contains($prompt->prompt, 'Preview: Rooms by the water')
                // The slug is not a source; the field being written is not either.
                && ! str_contains($prompt->prompt, 'lake-hotel')
                && str_contains($prompt->prompt, 'simple HTML')
                && str_contains($prompt->agent->instructions(), 'Demo shop');
        });

        $this->assertSame(0, Article::query()->count());
    }

    public function test_without_a_queue_the_job_runs_after_the_response(): void
    {
        config(['atrium-ai.queue.connection' => null, 'queue.default' => 'database']);
        Writer::fake(['A cosy hotel by the lake.']);

        // The answer goes out first; the kernel runs the job when it terminates.
        $id = $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ])->assertStatus(202)->assertJsonPath('status', 'pending')->json('id');

        $this->getJson('/admin/ai/generate/'.$id)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('value', 'A cosy hotel by the lake.');
    }

    public function test_the_job_after_the_response_is_given_the_time_it_needs(): void
    {
        config(['atrium-ai.queue.connection' => '', 'queue.default' => 'database', 'atrium-ai.timeout' => 400]);
        Writer::fake(['A cosy hotel by the lake.']);
        set_time_limit(0);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ])->assertStatus(202);

        // The job's own time is the longer of 300 seconds and one provider timeout.
        $this->assertSame('400', ini_get('max_execution_time'));
    }

    public function test_the_usage_is_logged_without_the_texts(): void
    {
        Writer::fake(['Short preview.']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'preview_text',
            'values' => ['detail_text' => '<p>Long secret text about the hotel.</p>'],
        ])->assertJsonPath('value', 'Short preview.');

        $usage = Usage::query()->sole();
        $this->assertSame('preview', $usage->task);
        $this->assertSame('articles', $usage->module);
        $this->assertSame('gemini', $usage->provider);
        $this->assertTrue($usage->succeeded);
        $this->assertNotNull($usage->admin_user_id);
        $this->assertStringNotContainsString('secret', json_encode($usage->getAttributes()));
    }

    public function test_the_next_provider_answers_when_the_first_is_over_its_limit(): void
    {
        Writer::fake(function (string $prompt, $attachments, $provider) {
            if ($provider->name() === 'gemini') {
                throw RateLimitedException::forProvider('gemini', 429);
            }

            return 'From Groq.';
        });

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ])->assertJsonPath('value', 'From Groq.')->assertJsonPath('provider', 'groq');
    }

    public function test_when_every_provider_is_busy_the_editor_reads_why(): void
    {
        Writer::fake(fn () => throw RateLimitedException::forProvider('any', 429));

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ])->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', __('atrium-ai::ai.errors.busy'));

        $this->assertFalse(Usage::query()->sole()->succeeded);
    }

    public function test_the_answer_follows_the_language_being_edited(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Kurzer Text.']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'preview_text',
            'locale' => 'de',
            'values' => ['detail_text' => 'Ein Hotel am See.'],
        ])->assertJsonPath('value', 'Kurzer Text.');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->agent->instructions(), 'Write in German (Deutsch, de)'));
    }

    public function test_a_translatable_field_is_translated_into_the_other_languages(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'sort' => 300]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Hotel am See', 'Hôtel au bord du lac']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'preview_text',
            'locale' => 'en',
            'translate' => true,
            'values' => ['preview_text' => ['en' => 'Lake hotel', 'de' => '', 'fr' => '']],
        ])->assertJsonPath('values', ['de' => 'Hotel am See', 'fr' => 'Hôtel au bord du lac']);

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'into German (Deutsch, de)') && str_contains($prompt->prompt, 'Lake hotel'));
        $this->assertSame(2, Usage::query()->count());
    }

    public function test_a_field_without_translations_cannot_be_translated(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'translate' => true,
            'values' => ['detail_text' => 'Text'],
        ])->assertUnprocessable()->assertJsonValidationErrors('field');
    }

    public function test_the_seo_title_keeps_to_the_field_length(): void
    {
        Writer::fake(['"'.str_repeat('Very long title ', 10).'"']);

        $value = $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'meta_title',
            'values' => ['name' => 'Lake Hotel', 'detail_text' => 'Rooms by the water.'],
        ])->json('value');

        $this->assertLessThanOrEqual(40, mb_strlen($value));
        $this->assertStringStartsWith('Very long title', $value);
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'At most 40 characters'));
    }

    public function test_alt_text_sends_the_picture_to_a_vision_provider(): void
    {
        $dir = public_path('storage/ai-test');
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/lake.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        Writer::fake(['A wooden pier on a calm lake.']);

        try {
            $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
                'module' => 'articles',
                'field' => 'picture_alt',
                'values' => ['name' => 'Lake Hotel', 'picture' => '/storage/ai-test/lake.png'],
            ])->assertJsonPath('value', 'A wooden pier on a calm lake.');
        } finally {
            @unlink($dir.'/lake.png');
            @rmdir($dir);
        }

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => count($prompt->attachments) === 1 && str_contains($prompt->prompt, 'Name: Lake Hotel'));
        $this->assertSame('alt', Usage::query()->sole()->task);
    }

    public function test_alt_text_never_reads_a_file_outside_the_web_root_or_another_host(): void
    {
        foreach (['/../.env', '../../composer.json', 'https://example.org/cat.jpg', '/storage/missing.jpg'] as $picture) {
            $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
                'module' => 'articles',
                'field' => 'picture_alt',
                'values' => ['picture' => $picture],
            ])->assertUnprocessable()->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.no_image'));
        }
    }

    public function test_nothing_to_work_from_is_said_before_any_call(): void
    {
        Writer::fake()->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'preview_text',
            'values' => ['detail_text' => '   '],
        ])->assertUnprocessable()->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.no_source'));
    }

    public function test_an_empty_named_source_falls_back_to_the_rest_of_the_form(): void
    {
        Writer::fake(['Short preview.']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'preview_text',
            'values' => ['name' => 'Lake Hotel', 'detail_text' => ''],
        ])->assertJsonPath('value', 'Short preview.');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Name: Lake Hotel'));
    }

    public function test_only_fields_with_ai_can_be_generated(): void
    {
        foreach (['code', 'picture', 'nope'] as $field) {
            $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
                'module' => 'articles',
                'field' => $field,
                'values' => ['name' => 'x'],
            ])->assertUnprocessable();
        }

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'nope',
            'field' => 'name',
        ])->assertNotFound();
    }

    public function test_it_takes_the_right_to_edit_the_module(): void
    {
        Writer::fake(['Text']);

        $this->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text'])->assertUnauthorized();

        $this->actingAs($this->editor(['articles.view']), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ])->assertForbidden();
    }

    public function test_a_result_is_only_for_whoever_asked(): void
    {
        Queue::fake();

        $id = $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ])->assertJsonPath('status', 'pending')->json('id');

        $this->getJson('/admin/ai/generate/'.$id)->assertJsonPath('status', 'pending');

        $this->actingAs($this->editor(['articles.update']), 'admin')
            ->getJson('/admin/ai/generate/'.$id)
            ->assertNotFound();
    }

    public function test_the_chain_skips_providers_without_a_key(): void
    {
        config(['atrium-ai.chains.text' => 'openrouter:meta-llama/llama-3.3-70b-instruct:free,groq:openai/gpt-oss-20b,gemini,groq']);

        $this->assertSame(['groq' => 'openai/gpt-oss-20b', 'gemini' => null], app(Providers::class)->chain('text'));

        config(['atrium-ai.chains.text' => ['ollama' => 'qwen3.5:4b']]);
        $this->assertSame(['ollama' => 'qwen3.5:4b'], app(Providers::class)->chain('text'));
    }
}
