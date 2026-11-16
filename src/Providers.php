<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

/**
 * The provider chains of config('atrium-ai.chains'), keeping only the
 * providers that can answer: the ones with a key in config/ai.php, and
 * Ollama, which needs none.
 */
final class Providers
{
    public const KINDS = ['text', 'vision', 'embeddings'];

    /**
     * Provider name => model (null = the SDK's default for that provider), in
     * the order they are asked. Empty when nobody can answer.
     *
     * @return array<string,string|null>
     */
    public function chain(string $kind): array
    {
        $chain = [];

        foreach ($this->entries($kind) as [$provider, $model]) {
            if (! array_key_exists($provider, $chain) && $this->usable($provider)) {
                $chain[$provider] = $model;
            }
        }

        return $chain;
    }

    public function available(string $kind): bool
    {
        return (bool) config('atrium-ai.enabled', true) && $this->chain($kind) !== [];
    }

    /**
     * Every entry of a chain as written, with whether it has a key: for
     * atrium:ai:status.
     *
     * @return array<int,array{provider:string,model:string|null,usable:bool}>
     */
    public function describe(string $kind): array
    {
        return array_map(fn (array $entry): array => [
            'provider' => $entry[0],
            'model' => $entry[1],
            'usable' => $this->usable($entry[0]),
        ], $this->entries($kind));
    }

    /**
     * "gemini,groq:llama-3.3-70b,openrouter:meta-llama/x:free" or the same as
     * an array. Only the first colon splits: model names have their own.
     *
     * @return array<int,array{0:string,1:string|null}>
     */
    private function entries(string $kind): array
    {
        $chain = config("atrium-ai.chains.{$kind}", []);
        $items = is_array($chain) ? $chain : explode(',', (string) $chain);
        $entries = [];

        foreach ($items as $key => $item) {
            // ['gemini' => 'gemini-3-flash'] as well as ['gemini:gemini-3-flash'].
            $item = is_string($key) ? $key.':'.$item : (string) $item;
            [$provider, $model] = array_pad(explode(':', trim($item), 2), 2, null);

            if ($provider !== '') {
                $entries[] = [$provider, $model === null || $model === '' ? null : $model];
            }
        }

        return $entries;
    }

    private function usable(string $provider): bool
    {
        $config = config("ai.providers.{$provider}");

        if (! is_array($config)) {
            return false;
        }

        if (($config['driver'] ?? $provider) === 'ollama') {
            return true;
        }

        return is_string($config['key'] ?? null) && trim($config['key']) !== '';
    }
}
