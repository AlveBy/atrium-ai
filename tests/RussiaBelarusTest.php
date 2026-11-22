<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Agents\Helper;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\AiServiceProvider;
use Atrium\Ai\GigaChat\GigaChatGateway;
use Atrium\Ai\GigaChat\GigaChatProvider;
use Atrium\Ai\Providers;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiManager;
use Laravel\Ai\Responses\AgentResponse;
use RuntimeException;

/**
 * YandexGPT, GigaChat and DeepSeek, for where Gemini and Groq refuse the
 * region. The HTTP calls are faked: no key is real.
 */
final class RussiaBelarusTest extends TestCase
{
    private const YANDEX = 'https://llm.api.cloud.yandex.net/v1/chat/completions';

    private const GIGACHAT = 'https://gigachat.devices.sberbank.ru/api/v1/chat/completions';

    private const OAUTH = 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth';

    private const DEEPSEEK = 'https://api.deepseek.com/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /**
     * @return array<string,mixed>
     */
    private function completion(string $text): array
    {
        return [
            'id' => 'chatcmpl-1',
            'model' => 'm',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function oauth(string $token = 'access-1', ?int $expiresInSeconds = 1800): array
    {
        return ['access_token' => $token, 'expires_at' => (now()->getTimestamp() + $expiresInSeconds) * 1000];
    }

    private function write(string $kind = 'text'): AgentResponse
    {
        return app(Providers::class)->ask($kind, fn (array $chain): AgentResponse => (new Writer('Be brief.'))->prompt('Say hi', provider: $chain));
    }

    private function chain(string $chain): void
    {
        config(['atrium-ai.chains.text' => $chain]);
    }

    public function test_the_providers_of_the_module_join_the_sdk_s(): void
    {
        $this->assertSame('openai-compatible', config('ai.providers.yandex.driver'));
        $this->assertSame('gigachat', config('ai.providers.gigachat.driver'));
        $this->assertSame('https://llm.api.cloud.yandex.net/v1', config('ai.providers.yandex.url'));
        $this->assertSame('GIGACHAT_API_PERS', config('ai.providers.gigachat.scope'));
        $this->assertSame('GigaChat', config('ai.providers.gigachat.models.text.default'));
    }

    public function test_a_provider_of_the_same_name_in_the_apps_own_config_is_left_alone(): void
    {
        config(['ai.providers.yandex' => ['driver' => 'openai-compatible', 'url' => 'https://mine.test/v1', 'key' => 'k']]);

        (new AiServiceProvider($this->app))->boot();

        $this->assertSame('https://mine.test/v1', config('ai.providers.yandex.url'));
    }

    public function test_a_config_without_providers_is_no_error(): void
    {
        config(['atrium-ai.providers' => null]);

        (new AiServiceProvider($this->app))->boot();

        $this->assertSame('gigachat', config('ai.providers.gigachat.driver'));
    }

    public function test_neither_is_asked_without_its_key(): void
    {
        $this->chain('yandex,gigachat,deepseek');
        config(['ai.providers.yandex.key' => null, 'ai.providers.gigachat.key' => '', 'ai.providers.deepseek.key' => null]);

        $this->assertSame([], app(Providers::class)->chain('text'));

        config(['ai.providers.yandex.key' => 'k', 'ai.providers.gigachat.key' => 'k', 'ai.providers.deepseek.key' => 'k']);

        $this->assertSame([['yandex', null], ['gigachat', null], ['deepseek', null]], app(Providers::class)->chain('text'));
    }

    public function test_the_model_of_yandexgpt_keeps_its_colons_and_slashes_in_a_chain(): void
    {
        config(['ai.providers.yandex.key' => 'k']);
        $this->chain('yandex:gpt://b1g123/yandexgpt/latest,yandex:gpt://b1g123/yandexgpt-lite/latest');

        $this->assertSame(
            [['yandex', 'gpt://b1g123/yandexgpt/latest'], ['yandex', 'gpt://b1g123/yandexgpt-lite/latest']],
            app(Providers::class)->chain('text'),
        );
    }

    public function test_yandexgpt_answers_with_the_models_uri_from_the_chain(): void
    {
        Http::fake([self::YANDEX => Http::response($this->completion('Привет'))]);
        config(['ai.providers.yandex.key' => 'yandex-key']);
        $this->chain('yandex:gpt://b1g123/yandexgpt/latest');

        $response = $this->write();

        $this->assertSame('Привет', $response->text);
        $this->assertSame('yandex', $response->meta->provider);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::YANDEX
            && $request->hasHeader('Authorization', 'Bearer yandex-key')
            && $request['model'] === 'gpt://b1g123/yandexgpt/latest');
    }

    public function test_yandexgpt_builds_its_model_from_the_folder_when_the_chain_names_none(): void
    {
        Http::fake([self::YANDEX => Http::response($this->completion('ok'))]);
        config(['ai.providers.yandex.key' => 'k', 'ai.providers.yandex.models.text.default' => 'gpt://b1g9/yandexgpt/latest']);
        $this->chain('yandex');

        $this->write();

        Http::assertSent(fn (Request $request): bool => $request['model'] === 'gpt://b1g9/yandexgpt/latest');
    }

    public function test_the_folder_of_yandex_makes_the_default_model(): void
    {
        $this->assertNull(config('ai.providers.yandex.models.text.default'));

        putenv('YANDEX_FOLDER_ID=b1g42');
        $_ENV['YANDEX_FOLDER_ID'] = 'b1g42';

        try {
            $config = require __DIR__.'/../config/atrium-ai.php';
        } finally {
            putenv('YANDEX_FOLDER_ID');
            unset($_ENV['YANDEX_FOLDER_ID']);
        }

        $this->assertSame('gpt://b1g42/yandexgpt/latest', $config['providers']['yandex']['models']['text']['default']);
    }

    public function test_gigachat_gets_a_token_from_its_authorization_key_and_asks_with_it(): void
    {
        Http::fake([self::OAUTH => Http::response($this->oauth('access-1')), self::GIGACHAT => Http::response($this->completion('Привет'))]);
        config(['ai.providers.gigachat.key' => 'base64-auth-key']);
        $this->chain('gigachat');

        $response = $this->write();

        $this->assertSame('Привет', $response->text);
        $this->assertSame('gigachat', $response->meta->provider);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::OAUTH
            && $request->hasHeader('Authorization', 'Basic base64-auth-key')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && preg_match('/^[0-9a-f-]{36}$/', $request->header('RqUID')[0]) === 1
            && $request->body() === 'scope=GIGACHAT_API_PERS');
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GIGACHAT
            && $request->hasHeader('Authorization', 'Bearer access-1')
            && $request['model'] === 'GigaChat');
    }

    public function test_the_scope_and_the_model_are_the_ones_set(): void
    {
        Http::fake([self::OAUTH => Http::response($this->oauth()), self::GIGACHAT => Http::response($this->completion('ok'))]);
        config(['ai.providers.gigachat.key' => 'k', 'ai.providers.gigachat.scope' => 'GIGACHAT_API_CORP']);
        $this->chain('gigachat:GigaChat-Pro');

        $this->write();

        Http::assertSent(fn (Request $request): bool => $request->url() === self::OAUTH && $request->body() === 'scope=GIGACHAT_API_CORP');
        Http::assertSent(fn (Request $request): bool => $request->url() === self::GIGACHAT && $request['model'] === 'GigaChat-Pro');
    }

    public function test_the_token_is_kept_until_a_minute_before_it_runs_out(): void
    {
        Http::fake([self::OAUTH => Http::response($this->oauth('access-1', 1800)), self::GIGACHAT => Http::response($this->completion('ok'))]);
        config(['ai.providers.gigachat.key' => 'k']);
        $this->chain('gigachat');

        $this->write();
        $this->assertSame('access-1', Cache::get('atrium-ai:gigachat-token:'.sha1('k|GIGACHAT_API_PERS')));
        $this->write();
        Http::assertSentCount(3);

        $this->travel(1738)->seconds();
        $this->write();
        Http::assertSentCount(4);

        $this->travel(2)->seconds();
        $this->write();
        Http::assertSentCount(6);
    }

    public function test_a_token_belongs_to_its_key_and_scope(): void
    {
        Http::fake([self::OAUTH => Http::response($this->oauth()), self::GIGACHAT => Http::response($this->completion('ok'))]);
        $this->chain('gigachat');

        config(['ai.providers.gigachat.key' => 'one']);
        $this->write();
        // The SDK keeps the provider it built; a new key is a new provider.
        app(AiManager::class)->forgetInstance('gigachat');
        config(['ai.providers.gigachat.key' => 'two']);
        $this->write();
        app(AiManager::class)->forgetInstance('gigachat');
        config(['ai.providers.gigachat.scope' => 'GIGACHAT_API_B2B']);
        $this->write();

        Http::assertSentCount(6);
    }

    public function test_a_response_without_a_token_is_an_error_and_is_not_kept(): void
    {
        Http::fake([self::OAUTH => Http::response(['expires_at' => 1]), self::GIGACHAT => Http::response($this->completion('ok'))]);
        config(['ai.providers.gigachat.key' => 'k']);
        $this->chain('gigachat');

        try {
            $this->write();
            $this->fail('No token, no answer.');
        } catch (RuntimeException $e) {
            $this->assertSame('GigaChat gave no access token.', $e->getMessage());
        }

        Http::assertNotSent(fn (Request $request): bool => $request->url() === self::GIGACHAT);

        Http::fake([self::OAUTH => Http::response(['access_token' => '', 'expires_at' => 1])]);
        $this->expectException(RuntimeException::class);
        $this->write();
    }

    public function test_a_refused_authorization_key_is_the_callers_error(): void
    {
        Http::fake([self::OAUTH => Http::response(['message' => 'Unauthorized'], 401)]);
        config(['ai.providers.gigachat.key' => 'wrong']);
        $this->chain('gigachat');

        $this->expectException(\Throwable::class);
        $this->write();
    }

    public function test_the_gateway_of_a_provider_is_one(): void
    {
        config(['ai.providers.gigachat.key' => 'k']);
        $provider = app(AiManager::class)->textProvider('gigachat');

        $this->assertSame($provider->textGateway(), $provider->textGateway());
    }

    public function test_the_certificate_is_always_checked_against_the_bundle_when_there_is_one_and_the_system_store_when_not(): void
    {
        $this->assertTrue(GigaChatGateway::verify([]));
        $this->assertTrue(GigaChatGateway::verify(['ca_bundle' => null]));
        $this->assertTrue(GigaChatGateway::verify(['ca_bundle' => '']));
        $this->assertSame('/etc/ssl/russian-ca.pem', GigaChatGateway::verify(['ca_bundle' => '/etc/ssl/russian-ca.pem']));
    }

    public function test_both_calls_of_gigachat_carry_the_bundle(): void
    {
        $verify = [];
        Http::fake(function (Request $request, array $options) use (&$verify) {
            $verify[$request->url()] = $options['verify'] ?? null;

            return str_contains($request->url(), 'oauth') ? Http::response($this->oauth()) : Http::response($this->completion('ok'));
        });
        config(['ai.providers.gigachat.key' => 'k', 'ai.providers.gigachat.ca_bundle' => '/etc/ssl/russian-ca.pem']);
        $this->chain('gigachat');

        $this->write();

        $this->assertSame([self::OAUTH => '/etc/ssl/russian-ca.pem', self::GIGACHAT => '/etc/ssl/russian-ca.pem'], $verify);
    }

    public function test_without_a_bundle_both_calls_of_gigachat_verify_the_certificate_with_the_system_store(): void
    {
        $verify = [];
        Http::fake(function (Request $request, array $options) use (&$verify) {
            $verify[$request->url()] = $options['verify'] ?? null;

            return str_contains($request->url(), 'oauth') ? Http::response($this->oauth()) : Http::response($this->completion('ok'));
        });
        config(['ai.providers.gigachat.key' => 'k']);
        $this->chain('gigachat');

        $this->write();

        $this->assertSame([self::OAUTH => true, self::GIGACHAT => true], $verify);
    }

    public function test_the_sdk_builds_the_gigachat_driver_once_it_is_resolved_before_or_after_the_module(): void
    {
        config(['ai.providers.gigachat.key' => 'k']);

        $this->assertInstanceOf(GigaChatProvider::class, app(AiManager::class)->textProvider('gigachat'));
    }

    public function test_deepseek_answers_the_buttons(): void
    {
        Http::fake([self::DEEPSEEK => Http::response($this->completion('Привет'))]);
        config(['ai.providers.deepseek.key' => 'deepseek-key']);
        $this->chain('deepseek');

        $response = $this->write();

        $this->assertSame('Привет', $response->text);
        $this->assertSame('deepseek', $response->meta->provider);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::DEEPSEEK && $request->hasHeader('Authorization', 'Bearer deepseek-key'));
    }

    public function test_deepseek_answers_the_helper(): void
    {
        Http::fake([self::DEEPSEEK => Http::response($this->completion('По документации'))]);
        config(['ai.providers.deepseek.key' => 'deepseek-key']);
        $this->chain('deepseek');

        $response = app(Providers::class)->ask('text', fn (array $chain): AgentResponse => (new Helper('Help.'))->prompt('How?', provider: $chain));

        $this->assertSame('По документации', $response->text);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::DEEPSEEK);
    }

    public function test_the_chain_of_the_region_asks_the_next_one_when_one_is_limited(): void
    {
        Http::fake([
            self::YANDEX => Http::response(['error' => ['message' => 'Too many requests']], 429),
            self::GIGACHAT.'*' => Http::response($this->completion('from gigachat')),
            self::OAUTH => Http::response($this->oauth()),
        ]);
        config(['ai.providers.yandex.key' => 'k', 'ai.providers.yandex.models.text.default' => 'gpt://b1g/yandexgpt/latest', 'ai.providers.gigachat.key' => 'k']);
        $this->chain('yandex,gigachat');

        $this->assertSame('from gigachat', $this->write()->text);
    }
}
