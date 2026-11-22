<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Agents\Helper;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Assistant;
use Atrium\Ai\Exceptions\NotConfigured;
use Atrium\Ai\Jobs\Answer;
use Atrium\Ai\Providers;
use Atrium\Ai\Results;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The chain goes on to the next entry (a provider, or the same provider with
 * another model) when one is over its limit, overloaded or refuses the region,
 * and does not ask a provider again that refused the region a moment ago.
 */
final class FailoverTest extends TestCase
{
    /** @var list<string> the "provider:model" of every call, in order */
    private array $asked = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['ai.providers.openrouter.key' => 'test-openrouter-key']);
    }

    /**
     * @param  array<string,mixed>  $body
     */
    private function http(int $status, array $body = []): RequestException
    {
        return new RequestException(new Response(new PsrResponse($status, [], (string) json_encode($body))));
    }

    /**
     * Asks the chain; every entry in $failures throws its exception, the others answer.
     *
     * @param  array<string,\Throwable>  $failures  "provider:model" => what it throws
     */
    private function ask(string $kind, array $failures = []): string
    {
        return app(Providers::class)->ask($kind, function (array $chain) use ($failures): string {
            $entry = array_key_first($chain).':'.current($chain);
            $this->asked[] = $entry;

            return isset($failures[$entry]) ? throw $failures[$entry] : "answer of {$entry}";
        });
    }

    /**
     * @return array<string,array{0:\Closure():\Throwable}>
     */
    public static function failovers(): array
    {
        return [
            'over its limit' => [static fn () => RateLimitedException::forProvider('gemini', 429)],
            'overloaded' => [static fn () => ProviderOverloadedException::forProvider('gemini', 503)],
        ];
    }

    #[DataProvider('failovers')]
    public function test_an_entry_that_is_limited_or_overloaded_hands_over_to_the_next(\Closure $failure): void
    {
        config(['atrium-ai.chains.text' => 'gemini:flash,groq:llama']);

        $this->assertSame('answer of groq:llama', $this->ask('text', ['gemini:flash' => $failure()]));
        $this->assertSame(['gemini:flash', 'groq:llama'], $this->asked);

        // Not a refusal of the region: the entry is asked again next time.
        $this->asked = [];
        $this->assertSame('answer of groq:llama', $this->ask('text', ['gemini:flash' => $failure()]));
        $this->assertSame(['gemini:flash', 'groq:llama'], $this->asked);
    }

    public function test_another_model_of_the_same_provider_is_the_next_entry(): void
    {
        config(['atrium-ai.chains.text' => 'openrouter:google/gemma-4-31b-it:free,openrouter:nvidia/nemotron-3.5-super-vl:free']);

        $answer = $this->ask('text', ['openrouter:google/gemma-4-31b-it:free' => RateLimitedException::forProvider('openrouter', 429)]);

        $this->assertSame('answer of openrouter:nvidia/nemotron-3.5-super-vl:free', $answer);
        $this->assertSame(['openrouter:google/gemma-4-31b-it:free', 'openrouter:nvidia/nemotron-3.5-super-vl:free'], $this->asked);
    }

    public function test_the_entries_are_asked_in_order_and_the_first_that_answers_ends_it(): void
    {
        config(['atrium-ai.chains.text' => 'gemini:flash,groq:llama']);

        $this->assertSame('answer of gemini:flash', $this->ask('text'));
        $this->assertSame(['gemini:flash'], $this->asked);
    }

    /**
     * @return array<string,array{0:int,1:array<string,mixed>}>
     */
    public static function refusals(): array
    {
        return [
            'a 403' => [403, ['error' => ['message' => 'Forbidden']]],
            'a 403 with no body' => [403, []],
            'Gemini\'s 400 for the region' => [400, ['error' => ['message' => 'User location is not supported for the API use.', 'code' => 'invalid_request']]],
            'the same in other letters' => [400, ['error' => ['message' => 'USER LOCATION IS NOT SUPPORTED']]],
        ];
    }

    /**
     * @param  array<string,mixed>  $body
     */
    #[DataProvider('refusals')]
    public function test_a_provider_that_refuses_the_region_hands_over_and_is_not_asked_again_for_a_while(int $status, array $body): void
    {
        config(['atrium-ai.chains.text' => 'gemini,groq']);

        $this->assertSame('answer of groq:', $this->ask('text', ['gemini:' => $this->http($status, $body)]));
        $this->assertSame(['gemini:', 'groq:'], $this->asked);
        $this->assertTrue(Cache::get('atrium-ai:refused:gemini'));
        $this->assertNull(Cache::get('atrium-ai:refused:groq'));

        // The next request goes straight to Groq.
        $this->asked = [];
        $this->assertSame('answer of groq:', $this->ask('text', ['gemini:' => $this->http($status, $body)]));
        $this->assertSame(['groq:'], $this->asked);
    }

    public function test_a_refusal_is_forgotten_after_the_time_set_for_it(): void
    {
        config(['atrium-ai.chains.text' => 'gemini,groq', 'atrium-ai.refused_for' => 600]);
        $failures = ['gemini:' => $this->http(403)];

        $this->ask('text', $failures);

        $this->travel(599)->seconds();
        $this->asked = [];
        $this->ask('text', $failures);
        $this->assertSame(['groq:'], $this->asked);

        $this->travel(2)->seconds();
        $this->asked = [];
        $this->ask('text', $failures);
        $this->assertSame(['gemini:', 'groq:'], $this->asked);
    }

    public function test_a_refusal_lasts_an_hour_unless_the_site_says_otherwise(): void
    {
        $this->forgetConfig('atrium-ai.refused_for');
        config(['atrium-ai.chains.text' => 'gemini,groq']);
        $failures = ['gemini:' => $this->http(403)];

        $this->ask('text', $failures);

        $this->travel(3599)->seconds();
        $this->asked = [];
        $this->ask('text', $failures);
        $this->assertSame(['groq:'], $this->asked);

        $this->travel(1)->seconds();
        $this->asked = [];
        $this->ask('text', $failures);
        $this->assertSame(['gemini:', 'groq:'], $this->asked);

        $this->assertSame(3600, (require __DIR__.'/../config/atrium-ai.php')['refused_for']);
    }

    public function test_a_refusal_is_the_provider_s_not_the_model_s(): void
    {
        config(['atrium-ai.chains.text' => 'openrouter:a:free,openrouter:b:free,groq']);

        $this->ask('text', ['openrouter:a:free' => $this->http(403)]);

        $this->asked = [];
        $this->ask('text');
        $this->assertSame(['groq:'], $this->asked);
    }

    public function test_when_every_provider_refused_the_last_refusal_is_what_the_caller_gets_and_all_are_tried_again(): void
    {
        config(['atrium-ai.chains.text' => 'gemini,groq']);
        $gemini = $this->http(400, ['error' => ['message' => 'User location is not supported for the API use.']]);
        $groq = $this->http(403, ['error' => ['message' => 'Forbidden']]);

        try {
            $this->ask('text', ['gemini:' => $gemini, 'groq:' => $groq]);
            $this->fail('Nobody answered, yet nothing was thrown.');
        } catch (RequestException $e) {
            $this->assertSame($groq, $e);
        }

        // Both are remembered, and then neither is better than the other: ask them again.
        $this->asked = [];
        $this->assertSame('answer of gemini:', $this->ask('text'));
        $this->assertSame(['gemini:'], $this->asked);
    }

    public function test_when_every_entry_is_over_its_limit_the_last_error_is_the_callers(): void
    {
        config(['atrium-ai.chains.text' => 'gemini,groq']);
        $last = RateLimitedException::forProvider('groq', 429);

        try {
            $this->ask('text', ['gemini:' => RateLimitedException::forProvider('gemini', 429), 'groq:' => $last]);
            $this->fail('Nobody answered, yet nothing was thrown.');
        } catch (RateLimitedException $e) {
            $this->assertSame($last, $e);
        }
    }

    /**
     * @return array<string,array{0:\Closure():\Throwable}>
     */
    public static function others(): array
    {
        $http = static fn (int $status, array $body = []): RequestException => new RequestException(new Response(new PsrResponse($status, [], (string) json_encode($body))));

        return [
            'a bad request that is not about the region' => [static fn () => $http(400, ['error' => ['message' => 'Unknown model']])],
            'a bad request with no message' => [static fn () => $http(400)],
            'an unauthorized key' => [static fn () => $http(401, ['error' => ['message' => 'Bad key']])],
            'a missing model' => [static fn () => $http(404)],
            'a server error' => [static fn () => $http(500)],
            'something else altogether' => [static fn () => new RuntimeException('Disk full')],
        ];
    }

    #[DataProvider('others')]
    public function test_any_other_error_is_not_skipped_over_or_remembered(\Closure $failure): void
    {
        config(['atrium-ai.chains.text' => 'gemini,groq']);
        $error = $failure();

        try {
            $this->ask('text', ['gemini:' => $error]);
            $this->fail('The error was swallowed.');
        } catch (\Throwable $e) {
            $this->assertSame($error, $e);
        }

        $this->assertSame(['gemini:'], $this->asked);

        // And nothing was remembered against Gemini.
        $this->asked = [];
        $this->ask('text');
        $this->assertSame(['gemini:'], $this->asked);
    }

    public function test_with_no_provider_that_can_answer_nothing_is_asked(): void
    {
        config(['atrium-ai.chains.text' => 'nosuch']);

        $this->expectException(NotConfigured::class);

        try {
            $this->ask('text');
        } finally {
            $this->assertSame([], $this->asked);
        }
    }

    public function test_the_buttons_fall_over_to_the_next_provider_when_the_first_refuses_the_region(): void
    {
        config(['atrium-ai.chains.text' => 'gemini,groq']);
        Writer::fake(function (string $prompt, $attachments, $provider) {
            throw_if($provider->name() === 'gemini', $this->http(400, ['error' => ['message' => 'User location is not supported for the API use.']]));

            return 'From Groq.';
        });

        $this->actingAs($this->admin(), 'admin')
            ->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake Hotel']])
            ->assertJsonPath('value', 'From Groq.')
            ->assertJsonPath('provider', 'groq');
    }

    public function test_the_api_falls_over_between_models_of_one_provider(): void
    {
        config(['atrium-ai.chains.text' => 'openrouter:a:free,openrouter:b:free']);
        Writer::fake(function (string $prompt, $attachments, $provider, string $model) {
            throw_if($model === 'a:free', RateLimitedException::forProvider('openrouter', 429));

            return "From {$model}.";
        });

        $this->actingAs($this->admin(), 'admin')
            ->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake Hotel']])
            ->assertJsonPath('value', 'From b:free.')
            ->assertJsonPath('model', 'b:free');
    }

    public function test_the_helper_falls_over_too(): void
    {
        config(['atrium-ai.chains.text' => 'gemini,groq']);
        Helper::fake(function (string $prompt, $attachments, $provider) {
            throw_if($provider->name() === 'gemini', $this->http(403, ['error' => ['message' => 'Forbidden']]));

            return 'From Groq.';
        });
        $admin = $this->admin();
        $id = app(Results::class)->start($admin->getKey());

        app()->call([new Answer($id, $admin->getKey(), 'Hello', [], 'en', 1), 'handle']);

        $this->assertSame('From Groq.', app(Results::class)->find($id, $admin->getKey())['value']);
    }

    public function test_embeddings_fall_over_too(): void
    {
        config(['atrium-ai.chains.embeddings' => 'gemini,openrouter']);
        Embeddings::fake(function (EmbeddingsPrompt $prompt) {
            throw_if($prompt->provider->name() === 'gemini', $this->http(403, ['error' => ['message' => 'Forbidden']]));

            return [[0.5, 0.25]];
        });

        $this->assertSame([[0.5, 0.25]], app(Assistant::class)->embed(['Lake']));
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->provider->name() === 'openrouter');
    }
}
