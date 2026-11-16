<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Agents\Writer;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * What the site owner changes without touching the package.
 */
final class ModuleTest extends TestCase
{
    public function test_a_prompt_is_replaced_by_a_view_of_the_site(): void
    {
        $dir = sys_get_temp_dir().'/atrium-ai-prompts-'.getmypid();
        @mkdir($dir);
        file_put_contents($dir.'/describe.blade.php', 'Our house style for {{ $field }}: {{ $text }}');
        view()->addNamespace('site', $dir);
        config(['atrium-ai.prompts.describe' => 'site::describe']);
        Writer::fake(['Text']);

        try {
            $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
                'module' => 'articles',
                'field' => 'detail_text',
                'values' => ['name' => 'Lake Hotel'],
            ])->assertJsonPath('value', 'Text');
        } finally {
            @unlink($dir.'/describe.blade.php');
            @rmdir($dir);
        }

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->prompt === 'Our house style for Description: Lake Hotel');
    }

    public function test_the_status_command_shows_the_chains(): void
    {
        $this->artisan('atrium:ai:status')
            ->expectsOutputToContain('gemini')
            ->assertSuccessful();
    }
}
