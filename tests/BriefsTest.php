<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Briefs;
use Atrium\Ai\Images;
use Atrium\Ai\Jobs\Generate;
use Atrium\Ai\Task;
use Atrium\Ai\Tests\Fixtures\Article;
use Atrium\Ai\Tests\Fixtures\Loose;
use Atrium\Ai\Tests\Fixtures\SeoArticle;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a request is turned into before any provider is asked, and which
 * pictures the "alt" task may read.
 */
final class BriefsTest extends TestCase
{
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /**
     * @return array<string,array{0:array<string,mixed>,1:bool}>
     */
    public static function translations(): array
    {
        return [
            'a text and another language' => [['preview_text' => ['en' => 'Lake hotel']], true],
            'no text in the language edited' => [['preview_text' => ['en' => '', 'de' => 'Hotel am See']], false],
            'spaces only' => [['preview_text' => ['en' => '   ']], false],
            'nothing sent' => [[], false],
        ];
    }

    /**
     * @param  array<string,mixed>  $values
     */
    #[DataProvider('translations')]
    public function test_a_translation_needs_a_text_to_translate(array $values, bool $made): void
    {
        $this->german();

        try {
            $brief = app(Briefs::class)->make(Article::class, 'preview_text', $values, 'en', translate: true);
            $this->assertTrue($made, 'Nothing to translate: the brief should be refused.');
            $this->assertSame(['de' => 'German (Deutsch, de)'], $brief->targets);
        } catch (ValidationException $e) {
            $this->assertFalse($made, 'A text to translate: the brief should be made.');
            $this->assertSame([__('atrium-ai::ai.errors.no_source')], $e->errors()['field']);
        }
    }

    public function test_a_site_with_one_language_has_nothing_to_translate_into(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('atrium-ai::ai.errors.no_source'));

        app(Briefs::class)->make(Article::class, 'preview_text', ['preview_text' => ['en' => 'Lake hotel']], 'en', translate: true);
    }

    public function test_a_model_outside_the_panel_has_no_ai_fields(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('atrium-ai::ai.errors.no_ai'));

        app(Briefs::class)->make(AdminUser::class, 'name', ['name' => 'Ann']);
    }

    public function test_a_model_that_is_not_administrable_has_no_ai_fields(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('atrium-ai::ai.errors.no_ai'));

        app(Briefs::class)->make(Loose::class, 'name', ['name' => 'Ann']);
    }

    public function test_the_fields_a_model_with_tabs_adds_are_written_too(): void
    {
        $brief = app(Briefs::class)->make(SeoArticle::class, 'meta_description', ['detail_text' => '<p>A hotel by the lake.</p>']);

        $this->assertSame(Task::SeoDescription, $brief->task);
        $this->assertSame(['Description' => 'A hotel by the lake.'], $brief->sources);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('atrium-ai::ai.errors.no_ai'));

        app(Briefs::class)->make(Article::class, 'meta_description', ['detail_text' => '<p>A hotel by the lake.</p>']);
    }

    public function test_a_picture_of_this_site_given_by_its_full_address_is_read(): void
    {
        $file = $this->picture(public_path('storage/ai-brief/lake.png'));

        $this->assertSame(realpath($file), app(Images::class)->local(url('/storage/ai-brief/lake.png').'?v=2#top'));
        $this->assertSame(realpath($file), app(Images::class)->local('//'.parse_url(url('/'), PHP_URL_HOST).'/storage/ai-brief/lake.png'));
        $this->assertSame(realpath($file), app(Images::class)->local('  /storage/ai-brief/lake.png  '));
        $this->assertNull(app(Images::class)->local('https://cdn.example.org/storage/ai-brief/lake.png'));
    }

    public function test_the_host_of_the_app_url_and_the_host_asked_are_both_this_server_in_any_case(): void
    {
        $file = $this->picture(public_path('storage/ai-brief/lake.png'));
        config(['app.url' => 'https://Shop.Example.com']);

        $this->assertSame(realpath($file), app(Images::class)->local('https://shop.example.com/storage/ai-brief/lake.png'));
        $this->assertSame(realpath($file), app(Images::class)->local('https://SHOP.EXAMPLE.COM/storage/ai-brief/lake.png'));
        $this->assertSame(realpath($file), app(Images::class)->local('http://LOCALHOST/storage/ai-brief/lake.png'));
        $this->assertNull(app(Images::class)->local('https://example.com/storage/ai-brief/lake.png'));
    }

    public function test_the_extension_of_a_picture_may_be_in_capitals(): void
    {
        $file = $this->picture(public_path('storage/ai-brief/LAKE.PNG'));

        $this->assertSame(realpath($file), app(Images::class)->local('/storage/ai-brief/LAKE.PNG'));
    }

    public function test_a_scheme_is_read_in_capitals_and_only_at_the_start(): void
    {
        $file = $this->picture(public_path('storage/ai-brief/lake.png'));
        Storage::fake('public');
        Storage::disk('public')->put('uploads/lake.png', (string) base64_decode(self::PIXEL));

        $this->assertSame(realpath($file), app(Images::class)->local('HTTP://LOCALHOST/storage/ai-brief/lake.png'));
        // Two slashes inside a path are not an address.
        $this->assertSame(realpath(Storage::disk('public')->path('uploads/lake.png')), app(Images::class)->local('uploads//lake.png'));
    }

    public function test_a_picture_on_the_public_disk_is_read_by_its_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/lake.png', (string) base64_decode(self::PIXEL));

        $this->assertSame(realpath(Storage::disk('public')->path('uploads/lake.png')), app(Images::class)->local('uploads/lake.png'));
        $this->assertNull(app(Images::class)->local('uploads/missing.png'));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function unreadable(): array
    {
        return [
            'nothing' => [''],
            'spaces' => ['   '],
            'up out of the web root' => ['/storage/../../.env'],
            'not a picture' => ['/storage/ai-brief/notes.txt'],
            'a folder' => ['/storage/ai-brief'],
            'this site without a path' => ['http://localhost'],
        ];
    }

    #[DataProvider('unreadable')]
    public function test_values_that_are_not_a_local_picture_are_not_read(string $value): void
    {
        $this->picture(public_path('storage/ai-brief/lake.png'));
        file_put_contents(public_path('storage/ai-brief/notes.txt'), 'notes');

        $this->assertNull(app(Images::class)->local($value));
    }

    public function test_a_link_to_a_folder_that_only_starts_like_the_web_root_is_not_read(): void
    {
        $neighbour = public_path().'-neighbour';
        $outside = $this->picture($neighbour.'/outside.png');
        @mkdir(public_path('storage/ai-brief'), 0777, true);
        symlink($neighbour, public_path('storage/ai-brief/folder'));

        try {
            $this->assertNull(app(Images::class)->local('/storage/ai-brief/folder/outside.png'));
        } finally {
            @unlink(public_path('storage/ai-brief/folder'));
            @unlink($outside);
            @rmdir($neighbour);
        }
    }

    public function test_an_address_of_this_site_is_read_when_the_app_url_has_no_host(): void
    {
        $file = $this->picture(public_path('storage/ai-brief/lake.png'));
        config(['app.url' => '']);

        $this->assertSame(realpath($file), app(Images::class)->local('http://localhost/storage/ai-brief/lake.png'));
        $this->assertNull(app(Images::class)->local('https://cdn.example.org/storage/ai-brief/lake.png'));
    }

    public function test_a_link_in_the_web_root_to_a_picture_elsewhere_is_not_read(): void
    {
        $outside = $this->picture(sys_get_temp_dir().'/atrium-ai-'.getmypid().'/outside.png');
        @mkdir(public_path('storage/ai-brief'), 0777, true);
        symlink($outside, public_path('storage/ai-brief/link.png'));

        try {
            $this->assertNull(app(Images::class)->local('/storage/ai-brief/link.png'));
        } finally {
            @unlink($outside);
            @rmdir(dirname($outside));
        }
    }

    public function test_a_job_goes_to_the_queue_that_is_configured(): void
    {
        Queue::fake();
        Writer::fake()->preventStrayPrompts();
        config(['atrium-ai.queue.connection' => 'database', 'atrium-ai.queue.queue' => 'ai']);

        $this->generate()->assertStatus(202);

        Queue::assertPushedOn('ai', Generate::class);
        Queue::assertPushed(Generate::class, fn (Generate $job): bool => $job->connection === 'database');
    }

    public function test_without_a_queue_name_a_job_goes_to_the_default_queue(): void
    {
        Queue::fake();
        Writer::fake()->preventStrayPrompts();
        config(['atrium-ai.queue.connection' => 'database', 'atrium-ai.queue.queue' => '']);

        $this->generate()->assertStatus(202);

        Queue::assertPushed(Generate::class, fn (Generate $job): bool => $job->connection === 'database' && $job->queue === null);
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('storage/ai-brief/*')) ?: [] as $file) {
            @unlink($file);
        }

        @rmdir(public_path('storage/ai-brief'));

        parent::tearDown();
    }

    private function german(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
    }

    private function picture(string $path): string
    {
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, (string) base64_decode(self::PIXEL));

        return $path;
    }

    private function generate(): TestResponse
    {
        return $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ]);
    }
}
