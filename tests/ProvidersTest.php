<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Providers;

/**
 * Which providers of the chains can answer.
 */
final class ProvidersTest extends TestCase
{
    public function test_a_chain_is_read_as_a_list_a_string_or_a_map_with_its_models(): void
    {
        config(['ai.providers.groq.key' => 'k', 'ai.providers.openrouter.key' => 'k']);

        config(['atrium-ai.chains.text' => 'gemini, groq:llama-3.3 ,openrouter:meta/x:free,gemini']);
        $this->assertSame(['gemini' => null, 'groq' => 'llama-3.3', 'openrouter' => 'meta/x:free'], app(Providers::class)->chain('text'));

        config(['atrium-ai.chains.text' => ['gemini' => 'flash', 'groq:llama']]);
        $this->assertSame(['gemini' => 'flash', 'groq' => 'llama'], app(Providers::class)->chain('text'));

        config(['atrium-ai.chains.text' => [7, 'gemini:', ':model', '']]);
        $this->assertSame(['gemini' => null], app(Providers::class)->chain('text'));
    }

    public function test_a_provider_that_is_not_set_up_is_not_usable_even_when_it_is_called_ollama(): void
    {
        $this->forgetConfig('ai.providers.ollama');
        config(['atrium-ai.chains.text' => 'ollama,gemini']);

        $this->assertSame(['gemini' => null], app(Providers::class)->chain('text'));
    }

    public function test_a_provider_of_the_ollama_driver_needs_no_key_whatever_it_is_called(): void
    {
        config(['ai.providers.local' => ['driver' => 'ollama'], 'ai.providers.paid' => ['driver' => 'openai'], 'atrium-ai.chains.text' => 'local,paid']);

        $this->assertSame(['local' => null], app(Providers::class)->chain('text'));
    }

    public function test_the_buttons_need_the_module_switched_on_and_a_provider(): void
    {
        $providers = app(Providers::class);

        $this->assertTrue($providers->available('text'));

        config(['atrium-ai.enabled' => false]);
        $this->assertFalse($providers->available('text'));

        config(['atrium-ai.enabled' => true, 'atrium-ai.chains.text' => 'nosuch']);
        $this->assertFalse($providers->available('text'));
    }

    public function test_the_providers_are_one_for_the_whole_application(): void
    {
        $this->assertSame(app(Providers::class), app(Providers::class));
    }
}
