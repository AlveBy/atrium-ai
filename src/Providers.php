<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Atrium\Ai\Exceptions\NotConfigured;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Exceptions\FailoverableException;
use Throwable;

/**
 * The provider chains of config('atrium-ai.chains'), keeping only the
 * providers that can answer: the ones with a key in config/ai.php, and
 * Ollama, which needs none.
 */
final class Providers
{
    public const KINDS = ['text', 'vision', 'embeddings'];

    /**
     * The entries that can answer, in the order they are asked: [provider,
     * model] (null = the SDK's default for that provider). A provider may
     * appear more than once with different models; the same pair twice is one.
     * Empty when nobody can answer.
     *
     * @return list<array{0:string,1:string|null}>
     */
    public function chain(string $kind): array
    {
        $chain = [];

        foreach ($this->entries($kind) as $entry) {
            if ($this->usable($entry[0]) && ! in_array($entry, $chain, true)) {
                $chain[] = $entry;
            }
        }

        return $chain;
    }

    /**
     * Asks the entries of a chain one after the other until one answers.
     * $ask gets a chain of that one entry (the SDK's `[provider => model]`).
     * The next entry is asked when the SDK says an entry is over its limit,
     * overloaded or out of reach, and when it refuses the region (a 403, or a
     * 400 "location is not supported"); that provider is then left out for
     * `atrium-ai.refused_for` seconds, so that every request does not pay the
     * round trip. Any other error is the caller's.
     *
     * @template T
     *
     * @param  Closure(array<string,string|null>):T  $ask
     * @return T
     */
    public function ask(string $kind, Closure $ask): mixed
    {
        $chain = $this->chain($kind);
        $open = array_filter($chain, fn (array $entry): bool => ! Cache::has($this->refusedKey($entry[0])));
        $last = null;

        // Every provider refused lately: try them again rather than none.
        foreach ($open === [] ? $chain : $open as [$provider, $model]) {
            try {
                return $ask([$provider => $model]);
            } catch (Throwable $e) {
                if (! $e instanceof FailoverableException && ! $this->refusesRegion($e)) {
                    throw $e;
                }

                if (! $e instanceof FailoverableException) {
                    Cache::put($this->refusedKey($provider), true, (int) config('atrium-ai.refused_for', 3600));
                }

                $last = $e;
            }
        }

        throw $last ?? NotConfigured::for($kind);
    }

    public function available(string $kind): bool
    {
        return config('atrium-ai.enabled', true) && $this->chain($kind) !== [];
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
            [$provider, $model] = [...explode(':', trim($item), 2), null];

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

    /**
     * A provider that does not serve this region: a 403, or Gemini's 400
     * "User location is not supported for the API use".
     */
    private function refusesRegion(Throwable $e): bool
    {
        return $e instanceof RequestException
            && ($e->response->status() === 403
                || ($e->response->status() === 400 && str_contains(strtolower((string) $e->response->json('error.message')), 'location is not supported')));
    }

    private function refusedKey(string $provider): string
    {
        return 'atrium-ai:refused:'.$provider;
    }
}
