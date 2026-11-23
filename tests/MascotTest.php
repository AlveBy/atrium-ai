<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Facades\Setting;
use Atrium\Ai\Agents\Helper;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Help\Tools\ListModules;
use Atrium\Ai\Help\Tools\SearchDocs;
use Atrium\Ai\Jobs\Answer;
use Atrium\Ai\Result;
use Atrium\Ai\Results;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The helper's face in the corner of the admin: whether it may be playful
 * (Modules → AI), its costume of the day, and what it is busy with while
 * the chat waits for the answer.
 */
final class MascotTest extends HelpTestCase
{
    public function test_the_helper_is_playful_until_the_setting_says_otherwise(): void
    {
        $this->assertTrue($this->help()['playful']);
    }

    public function test_the_settings_screen_offers_the_switch(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/modules/ai/settings')
            ->assertOk()
            ->assertSee('"key":"ai","name":"alveby\\/atrium-ai","label":"AI"', false)
            ->assertSee('Playful helper')
            ->assertSee(json_encode(__('atrium-ai::ai.settings.description'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), false);
    }

    /** @return array<string, array{0:mixed, 1:bool}> */
    public static function switches(): array
    {
        return [
            'off' => [false, false],
            'off as a form sends it' => ['0', false],
            'on' => [true, true],
            'on as a form sends it' => ['1', true],
        ];
    }

    #[DataProvider('switches')]
    public function test_the_switch_turns_the_moods_off_and_on(mixed $sent, bool $playful): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/modules/ai/settings', ['playful' => $sent])
            ->assertSessionHasNoErrors();

        $this->assertSame($playful, Setting::get('ai.playful'));
        $this->assertSame($playful, $this->help()['playful']);
    }

    public function test_a_value_that_is_not_yes_or_no_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put('/admin/modules/ai/settings', ['playful' => 'sometimes'])
            ->assertSessionHasErrors('playful');

        $this->assertTrue($this->help()['playful']);
    }

    public function test_only_who_may_set_up_the_module_changes_it(): void
    {
        $editor = $this->editor(['articles.view']);

        $this->actingAs($editor, 'admin')->get('/admin/modules/ai/settings')->assertForbidden();
        $this->actingAs($editor, 'admin')->put('/admin/modules/ai/settings', ['playful' => false])->assertForbidden();

        $this->assertTrue($this->help()['playful']);
    }

    /** @return array<string, array{0:string, 1:string|null}> */
    public static function days(): array
    {
        return [
            'the day before' => ['2026-12-23 23:59:59', null],
            'Christmas Eve' => ['2026-12-24 00:00:00', 'new_year'],
            'New Year\'s Eve' => ['2026-12-31 18:00:00', 'new_year'],
            'New Year' => ['2027-01-01 00:00:00', 'new_year'],
            'Orthodox Christmas' => ['2027-01-07 23:59:59', 'new_year'],
            'the day after' => ['2027-01-08 00:00:00', null],
            'summer' => ['2027-07-15 12:00:00', null],
        ];
    }

    #[DataProvider('days')]
    public function test_the_helper_wears_a_hat_around_the_new_year(string $now, ?string $holiday): void
    {
        $this->travelTo(Carbon::parse($now));

        $this->assertSame($holiday, $this->help()['holiday']);
    }

    /** @return array<string, array{0:string, 1:string}> */
    public static function tools(): array
    {
        return [
            'a guide' => ['search_docs', 'read'],
            'the records' => ['list_modules', 'search'],
        ];
    }

    #[DataProvider('tools')]
    public function test_a_tool_call_shows_on_the_waiting_answer(string $tool, string $step): void
    {
        $admin = $this->admin();
        $id = app(Results::class)->start($admin->id);
        $seen = [];
        Event::listen(ToolInvoked::class, function () use ($id, $admin, &$seen): void {
            $seen[] = app(Results::class)->find($id, $admin->id);
        });
        Helper::fake([new ToolCall('call-1', $tool, ['query' => 'sites']), 'Done.']);

        app()->call([new Answer($id, $admin->id, 'Where?', [], 'en', $this->mainSite()->id), 'handle']);

        $this->assertSame([['status' => 'pending', 'step' => $step]], $seen);
        $this->assertSame('done', app(Results::class)->find($id, $admin->id)['status']);
    }

    public function test_a_step_is_kept_only_on_an_answer_still_waiting(): void
    {
        $results = app(Results::class);
        $waiting = $results->start(1);
        $done = $results->start(1);
        $results->done($done, new Result(value: 'Here.'));

        $results->step($waiting, 'read');
        $results->step($done, 'read');
        $results->step('no-such-answer', 'read');

        $this->assertSame(['status' => 'pending', 'step' => 'read'], $results->find($waiting, 1));
        $this->assertSame('done', $results->find($done, 1)['status']);
        $this->assertArrayNotHasKey('step', $results->find($done, 1));
        $this->assertNull($results->find('no-such-answer', 1));
    }

    public function test_tool_calls_of_other_agents_change_no_answer(): void
    {
        $results = app(Results::class);
        $id = $results->start(1);
        $tool = new SearchDocs('en');

        event(new InvokingTool('inv', 'call', new Writer, $tool, []));
        event(new InvokingTool('inv', 'call', new Helper('Help.'), $tool, []));

        $this->assertSame(['status' => 'pending'], $results->find($id, 1));

        event(new InvokingTool('inv', 'call', new Helper('Help.', answer: $id), app()->make(ListModules::class, ['user' => $this->admin(), 'locale' => 'en']), []));

        $this->assertSame(['status' => 'pending', 'step' => 'search'], $results->find($id, 1));
    }

    /**
     * The helper's part of the props of an admin page.
     *
     * @return array<string, mixed>
     */
    private function help(): array
    {
        return $this->actingAs($this->admin(), 'admin')->get('/admin/articles')->viewData('page')['props']['ai']['help'];
    }
}
