<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

/**
 * Required with composer but not installed with atrium:module:install: no
 * table for the log, so nothing of the module shows.
 */
final class NotInstalledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.modules', []);
    }

    public function test_no_routes_and_no_buttons(): void
    {
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text'])->assertNotFound();

        $page = $this->actingAs($this->admin(), 'admin')->get('/admin/articles/create')->viewData('page');
        $this->assertArrayNotHasKey('ai', $page['props']);
    }
}
