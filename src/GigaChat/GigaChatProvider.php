<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\GigaChat;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use RuntimeException;

/**
 * GigaChat (Sber): its chat is OpenAI's, so the SDK's openai-compatible
 * gateway does the talking; what is its own is the key. The "key" of its
 * config is the authorization key from the GigaChat console, and every call
 * is signed with an access token that this class gets for it and keeps for
 * the 30 minutes it lives.
 */
final class GigaChatProvider extends OpenAiCompatibleProvider
{
    /**
     * @return array{key:string}
     */
    #[\Override]
    public function providerCredentials(): array
    {
        return ['key' => $this->token()];
    }

    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new GigaChatGateway($this->events);
    }

    private function token(): string
    {
        $key = $this->cacheKey();
        $token = Cache::get($key);

        if (is_string($token)) {
            return $token;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$this->config['key'],
            'RqUID' => (string) Str::uuid(),
            'Accept' => 'application/json',
        ])
            ->withOptions(['verify' => GigaChatGateway::verify($this->config)])
            ->asForm()
            ->post((string) $this->config['auth_url'], ['scope' => $this->config['scope']])
            ->throw();

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GigaChat gave no access token.');
        }

        // "expires_at" is a time in milliseconds; a minute early is safe.
        Cache::put($key, $token, Carbon::createFromTimestampMs((int) $response->json('expires_at'))->subMinute());

        return $token;
    }

    private function cacheKey(): string
    {
        return 'atrium-ai:gigachat-token:'.sha1($this->config['key'].'|'.$this->config['scope']);
    }
}
