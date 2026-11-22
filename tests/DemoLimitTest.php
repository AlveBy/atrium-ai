<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Ai\Agents\Helper;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\DemoLimit;
use Atrium\Ai\Tests\Concerns\CallsTheApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A public demo shares one administrator among all visitors: while the demo
 * mode is on the site as a whole gets a few provider calls a day, on every
 * way in (the panel's button, the helper, the API and the MCP tool).
 */
final class DemoLimitTest extends TestCase
{
    use CallsTheApi;

    private const BODY = ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The counter has a cache store of its own; the tests use the one in memory.
        $app['config']->set('atrium-ai.demo.store', 'array');
        $app['config']->set('atrium-ai.demo.daily_limit', 2);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('array')->flush();
    }

    private function demo(bool $on = true): void
    {
        config(['atrium.demo' => ['enabled' => $on]]);
    }

    private function token(): string
    {
        return $this->tokenOf(['articles.view', 'articles.update'], ['articles:read', 'articles:write']);
    }

    private function panel(): TestResponse
    {
        return $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', self::BODY);
    }

    private function api(string $token): TestResponse
    {
        return $this->withToken($token)->postJson('/api/v1/ai/generate', self::BODY);
    }

    private function help(): TestResponse
    {
        return $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => 'How many articles?']);
    }

    private function mcpGenerate(string $token): mixed
    {
        return $this->mcp($token, 'tools/call', ['name' => 'generate_text', 'arguments' => ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']]])->json('result');
    }

    private function used(): int
    {
        return (int) Cache::store('array')->get('atrium-ai:demo-calls:'.now()->toDateString());
    }

    private function addGerman(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
    }

    public function test_the_panel_stops_at_the_daily_limit_and_asks_no_provider(): void
    {
        $this->demo();
        Writer::fake(['Text']);

        $this->panel()->assertStatus(202)->assertJsonPath('status', 'done');
        $this->panel()->assertStatus(202);

        $this->panel()
            ->assertStatus(429)
            ->assertJsonPath('message', 'The demo\'s daily AI limit is used up. Try again tomorrow.');

        Writer::assertPrompted(fn () => true);
        $this->assertSame(2, $this->used());
    }

    public function test_the_answer_says_when_to_come_back(): void
    {
        $this->demo();
        $this->travelTo(now()->startOfDay()->setTime(22, 30));
        config(['atrium-ai.demo.daily_limit' => 1]);
        Writer::fake(['Text']);

        $this->panel()->assertStatus(202);
        $response = $this->panel()->assertStatus(429);

        // Until midnight: an hour and a half.
        $this->assertEqualsWithDelta(5400, (int) $response->headers->get('Retry-After'), 1);
    }

    public function test_the_api_stops_at_the_daily_limit_with_a_429(): void
    {
        $this->demo();
        Writer::fake(['Text']);
        $token = $this->token();

        $this->api($token)->assertOk()->assertJsonPath('data.value', 'Text');
        $this->api($token)->assertOk();

        $this->api($token)
            ->assertStatus(429)
            ->assertExactJson(['message' => 'The demo\'s daily AI limit is used up. Try again tomorrow.']);

        $this->assertSame(2, $this->used());
    }

    public function test_the_mcp_tool_stops_at_the_daily_limit_and_says_why(): void
    {
        $this->demo();
        Writer::fake(['Text']);
        $token = $this->token();

        $this->assertFalse($this->mcpGenerate($token)['isError']);
        $this->assertFalse($this->mcpGenerate($token)['isError']);

        $result = $this->mcpGenerate($token);

        $this->assertTrue($result['isError']);
        $this->assertSame("HTTP 429. The demo's daily AI limit is used up. Try again tomorrow.", $result['content'][0]['text']);
        $this->assertSame(2, $this->used());
    }

    public function test_the_helper_stops_at_the_daily_limit(): void
    {
        $this->demo();
        Helper::fake(['It is 3.']);

        $this->help()->assertStatus(202);
        $this->help()->assertStatus(202);
        $this->help()->assertStatus(429)->assertJsonPath('message', 'The demo\'s daily AI limit is used up. Try again tomorrow.');

        $this->assertSame(2, $this->used());
    }

    public function test_the_four_ways_in_share_one_allowance(): void
    {
        $this->demo();
        Writer::fake(['Text']);
        Helper::fake(['It is 3.']);
        $token = $this->token();
        config(['atrium-ai.demo.daily_limit' => 3]);

        $this->panel()->assertStatus(202);
        $this->api($token)->assertOk();
        $this->help()->assertStatus(202);

        $this->assertTrue($this->mcpGenerate($token)['isError']);
        $this->panel()->assertStatus(429);
        $this->api($token)->assertStatus(429);
        $this->help()->assertStatus(429);
    }

    public function test_the_allowance_is_the_site_s_not_the_visitor_s(): void
    {
        $this->demo();
        Writer::fake(['Text']);
        config(['atrium-ai.demo.daily_limit' => 1]);

        $this->panel()->assertStatus(202);

        // Another administrator signs in: the day's limit is already gone.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->editor(['articles.view', 'articles.update']), 'admin')
            ->postJson('/admin/ai/generate', self::BODY)
            ->assertStatus(429);
    }

    public function test_the_answer_at_the_last_second_still_says_to_wait_a_second(): void
    {
        $this->demo();
        $this->travelTo(now()->endOfDay());
        config(['atrium-ai.demo.daily_limit' => 1]);
        Writer::fake(['Text']);

        $this->panel()->assertStatus(202);

        $this->assertSame('1', $this->panel()->assertStatus(429)->headers->get('Retry-After'));
    }

    public function test_the_counter_of_a_day_is_forgotten_an_hour_after_the_day_ends(): void
    {
        $this->demo();
        $this->travelTo(now()->startOfDay()->setTime(12, 0));
        Writer::fake(['Text']);
        $this->panel()->assertStatus(202);
        $key = 'atrium-ai:demo-calls:'.now()->toDateString();

        $this->assertSame(1, Cache::store('array')->get($key));

        $this->travelTo(now()->endOfDay()->addHour()->subSeconds(2));
        $this->assertSame(1, Cache::store('array')->get($key));

        $this->travelTo(now()->addSeconds(4));
        $this->assertNull(Cache::store('array')->get($key));
    }

    public function test_the_limit_from_the_environment_is_a_string_and_counts_as_a_number(): void
    {
        $this->demo();
        config(['atrium-ai.demo.daily_limit' => '1']);
        Writer::fake(['Text']);

        $this->panel()->assertStatus(202);
        $this->panel()->assertStatus(429);
    }

    public function test_tomorrow_the_allowance_is_new(): void
    {
        $this->demo();
        Writer::fake(['Text']);
        $this->travelTo(now()->setTime(23, 59, 30));

        $this->panel();
        $this->panel();
        $this->panel()->assertStatus(429);

        $this->travel(2)->minutes();

        $this->panel()->assertStatus(202);
        $this->assertSame(1, $this->used());
    }

    public function test_a_translation_costs_a_call_per_language(): void
    {
        $this->demo();
        $this->addGerman();
        Language::query()->create(['code' => 'fr', 'name' => 'Français', 'sort' => 300]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Hallo']);
        config(['atrium-ai.demo.daily_limit' => 3]);
        $translate = ['module' => 'articles', 'field' => 'preview_text', 'locale' => 'en', 'translate' => true, 'values' => ['preview_text' => ['en' => 'Hello']]];

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $translate)->assertStatus(202);
        $this->assertSame(2, $this->used());

        // Two languages do not fit into the one call that is left: nothing is taken, nothing asked.
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $translate)->assertStatus(429);
        $this->assertSame(2, $this->used());

        // The one call that is left is still there for a text.
        $this->panel()->assertStatus(202);
        $this->assertSame(3, $this->used());
    }

    public function test_a_translation_over_the_api_costs_a_call_per_language_too(): void
    {
        $this->demo();
        $this->addGerman();
        Writer::fake(['Hallo']);
        config(['atrium-ai.demo.daily_limit' => 1]);
        $token = $this->token();
        $translate = ['module' => 'articles', 'field' => 'preview_text', 'locale' => 'en', 'translate' => true, 'values' => ['preview_text' => ['en' => 'Hello']]];

        $this->withToken($token)->postJson('/api/v1/ai/generate', $translate)->assertOk();
        $this->withToken($token)->postJson('/api/v1/ai/generate', $translate)->assertStatus(429);
        $this->assertSame(1, $this->used());
    }

    public function test_a_request_that_is_refused_before_anything_is_asked_costs_nothing(): void
    {
        $this->demo();
        Writer::fake(['Text']);
        $token = $this->token();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', ['module' => 'articles'])->assertUnprocessable();
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text'])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'detail_text'])->assertUnprocessable();
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/help', ['question' => ''])->assertUnprocessable();

        $this->assertSame(0, $this->used());
    }

    public function test_a_provider_that_fails_still_used_the_allowance(): void
    {
        $this->demo();
        config(['atrium-ai.demo.daily_limit' => 1]);
        Writer::fake(fn () => throw new \RuntimeException('Connection timed out'));
        $token = $this->token();

        $this->api($token)->assertStatus(503);
        $this->api($token)->assertStatus(429);
    }

    public function test_without_the_demo_mode_nothing_is_counted_or_limited(): void
    {
        $this->demo(false);
        Writer::fake(['Text']);
        Helper::fake(['It is 3.']);
        $token = $this->token();
        config(['atrium-ai.demo.daily_limit' => 1]);

        foreach (range(1, 3) as $ignored) {
            $this->panel()->assertStatus(202);
            $this->api($token)->assertOk();
            $this->help()->assertStatus(202);
            $this->assertFalse($this->mcpGenerate($token)['isError']);
        }

        $this->assertSame(0, $this->used());
    }

    public function test_a_site_with_no_demo_setting_at_all_is_not_a_demo(): void
    {
        $this->forgetConfig('atrium.demo');
        Writer::fake(['Text']);
        config(['atrium-ai.demo.daily_limit' => 1]);

        $this->panel()->assertStatus(202);
        $this->panel()->assertStatus(202);
    }

    public function test_a_limit_of_zero_is_no_ceiling(): void
    {
        $this->demo();
        Writer::fake(['Text']);
        config(['atrium-ai.demo.daily_limit' => 0]);

        foreach (range(1, 4) as $ignored) {
            $this->panel()->assertStatus(202);
        }

        $this->assertSame(0, $this->used());
    }

    public function test_a_demo_with_no_limit_set_gets_forty_calls_a_day(): void
    {
        $this->demo();
        $this->forgetConfig('atrium-ai.demo.daily_limit');
        Writer::fake(['Text']);

        $limit = app(DemoLimit::class);
        $limit->claim(40);

        $this->assertSame(40, $this->used());

        try {
            $limit->claim();
            $this->fail('The forty-first call was let through.');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
        }
    }

    public function test_the_count_is_kept_in_the_configured_store_and_in_a_file_by_default(): void
    {
        $this->demo();
        $this->forgetConfig('atrium-ai.demo.store');
        Cache::store('file')->flush();

        app(DemoLimit::class)->claim();

        $this->assertSame(1, Cache::store('file')->get('atrium-ai:demo-calls:'.now()->toDateString()));
        $this->assertSame(0, $this->used());
        Cache::store('file')->flush();
    }

    public function test_the_count_does_not_live_in_the_database_the_reset_empties(): void
    {
        $this->demo();
        config(['cache.default' => 'database']);

        app(DemoLimit::class)->claim();

        $this->assertDatabaseCount('ai_usage', 0);
        $this->assertSame(1, $this->used());
    }

    /**
     * @return array<string,array{0:bool}>
     */
    public static function modes(): array
    {
        return ['a demo' => [true], 'not a demo' => [false]];
    }

    #[DataProvider('modes')]
    public function test_the_pages_say_whether_this_is_a_public_demo(bool $demo): void
    {
        $this->demo($demo);

        $ai = $this->actingAs($this->admin(), 'admin')->get('/admin/articles')->assertOk()->viewData('page')['props']['ai'];

        $this->assertSame($demo, $ai['demo']);
        $this->assertSame('This demo is public: do not type personal data, other people\'s data or anything private.', $ai['labels']['demo_notice']);
    }

    public function test_a_site_with_no_demo_setting_at_all_does_not_say_it_is_a_demo(): void
    {
        $this->forgetConfig('atrium.demo');

        $this->assertFalse($this->actingAs($this->admin(), 'admin')->get('/admin/articles')->viewData('page')['props']['ai']['demo']);
    }

    public function test_the_daily_limit_and_the_store_come_from_the_environment(): void
    {
        $config = require __DIR__.'/../config/atrium-ai.php';
        $this->assertSame(40, $config['demo']['daily_limit']);
        $this->assertSame('file', $config['demo']['store']);

        putenv('ATRIUM_AI_DEMO_DAILY_LIMIT=7');
        putenv('ATRIUM_AI_DEMO_STORE=redis');
        $_ENV['ATRIUM_AI_DEMO_DAILY_LIMIT'] = '7';
        $_ENV['ATRIUM_AI_DEMO_STORE'] = 'redis';

        try {
            $config = require __DIR__.'/../config/atrium-ai.php';
            $this->assertSame(7, $config['demo']['daily_limit']);
            $this->assertSame('redis', $config['demo']['store']);
        } finally {
            putenv('ATRIUM_AI_DEMO_DAILY_LIMIT');
            putenv('ATRIUM_AI_DEMO_STORE');
            unset($_ENV['ATRIUM_AI_DEMO_DAILY_LIMIT'], $_ENV['ATRIUM_AI_DEMO_STORE']);
        }
    }

    public function test_every_language_has_the_notice_and_the_message(): void
    {
        foreach (array_keys(config('atrium.locales')) as $locale) {
            $strings = require __DIR__."/../lang/{$locale}/ai.php";

            $this->assertNotSame('', trim($strings['ui']['demo_notice']), $locale);
            $this->assertNotSame('', trim($strings['errors']['demo_limit']), $locale);
        }
    }
}
