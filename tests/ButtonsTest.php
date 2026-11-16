<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Tests\Fixtures\Article;

/**
 * Whether the admin draws the buttons at all.
 */
final class ButtonsTest extends TestCase
{
    public function test_the_form_gets_the_buttons_when_a_provider_has_a_key(): void
    {
        $page = $this->actingAs($this->admin(), 'admin')->get('/admin/articles/create')->assertOk()->viewData('page');

        $this->assertSame('/admin/ai/generate', $page['props']['ai']['url']);
        $this->assertContains('describe', $page['props']['ai']['tasks']);
        $this->assertContains('alt', $page['props']['ai']['tasks']);
        $this->assertSame('Generate', $page['props']['ai']['labels']['generate']);

        $fields = collect($page['props']['fieldsByTab'] ?? [])->flatten(1)->keyBy('name');
        if ($fields->isEmpty()) {
            $fields = collect($page['props']['fields'])->keyBy('name');
        }
        $this->assertSame(['task' => 'preview', 'from' => ['detail_text']], $fields['preview_text']['ai']);
        $this->assertNull($fields['code']['ai']);
    }

    public function test_no_key_no_buttons(): void
    {
        config(['ai.providers.gemini.key' => '', 'ai.providers.groq.key' => null]);

        $page = $this->actingAs($this->admin(), 'admin')->get('/admin/articles/create')->viewData('page');

        $this->assertNull($page['props']['ai']);
    }

    public function test_no_vision_provider_no_alt_button(): void
    {
        config(['atrium-ai.chains.vision' => 'openrouter:google/gemma-3-27b-it:free']);

        $page = $this->actingAs($this->admin(), 'admin')->get('/admin/articles/create')->viewData('page');

        $this->assertNotContains('alt', $page['props']['ai']['tasks']);
        $this->assertContains('translate', $page['props']['ai']['tasks']);
    }

    public function test_switched_off_no_buttons(): void
    {
        config(['atrium-ai.enabled' => false]);

        $page = $this->actingAs($this->admin(), 'admin')->get('/admin/articles/create')->viewData('page');

        $this->assertNull($page['props']['ai']);
        $this->assertSame(0, Article::query()->count());
    }
}
