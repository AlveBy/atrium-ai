<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Briefs;
use Atrium\Ai\Models\Usage;
use Atrium\Ai\Task;
use Atrium\Ai\Tests\Fixtures\Article;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * "Improve": the text already in the field, corrected, instead of a new one.
 */
final class ImproveTest extends TestCase
{
    /**
     * @param  array<string,mixed>  $extra
     * @return array<string,mixed>
     */
    private function body(string $field, array $values, array $extra = []): array
    {
        return ['module' => 'articles', 'field' => $field, 'improve' => true, 'values' => $values, ...$extra];
    }

    public function test_the_text_in_the_field_is_corrected_and_offered_not_saved(): void
    {
        Writer::fake(['<p>The hotel has <a href="/rooms">rooms</a> with a view.</p>']);
        $text = '<p>The hotel have <a href="/rooms">rooms</a> with a veiw</p>';

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['name' => 'Lake', 'detail_text' => $text]))
            ->assertStatus(202)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('value', '<p>The hotel has <a href="/rooms">rooms</a> with a view.</p>');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Improve the text of the field "Description"')
            // The text goes as it is, tags and all, and is the only thing the model is given.
            && str_contains($prompt->prompt, "Text:\n{$text}")
            && ! str_contains($prompt->prompt, 'Name: Lake')
            && str_contains($prompt->prompt, 'Keep the HTML as it is')
            && ! str_contains($prompt->prompt, 'Plain text')
            && ! str_contains($prompt->prompt, 'piece of a longer text')
            // The text's own language wins over the site's.
            && str_contains($prompt->agent->instructions(), 'in the language the text is written in')
            && ! str_contains($prompt->agent->instructions(), 'whatever language'));

        $this->assertSame(0, Article::query()->count());
        $this->assertSame(['improve'], Usage::query()->pluck('task')->all());
    }

    public function test_a_piece_picked_out_is_the_only_thing_sent_and_comes_back_alone(): void
    {
        Writer::fake(['rooms with a view']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => '<p>Big. The rooms with a veiw. Small.</p>'], ['selection' => 'rooms with a veiw']))
            ->assertJsonPath('value', 'rooms with a view');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Text:\nrooms with a veiw")
            && ! str_contains($prompt->prompt, 'Big.')
            && str_contains($prompt->prompt, 'piece of a longer text'));
    }

    public function test_a_selection_of_spaces_is_no_selection(): void
    {
        Writer::fake(['Whole']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => 'The whole text'], ['selection' => "  \n "]))
            ->assertJsonPath('value', 'Whole');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Text:\nThe whole text") && ! str_contains($prompt->prompt, 'piece of a longer text'));
    }

    public function test_a_selection_without_improve_is_not_used(): void
    {
        Writer::fake(['Text']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'selection' => 'Secret piece', 'values' => ['name' => 'Lake']])
            ->assertJsonPath('value', 'Text');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'Name: Lake') && ! str_contains($prompt->prompt, 'Secret piece'));
    }

    public function test_a_plain_field_is_improved_as_plain_text_within_its_limits(): void
    {
        Writer::fake(['Better text']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('preview_text', ['preview_text' => ['en' => 'Bad <b>text</b>']]))
            ->assertJsonPath('value', 'Better text');

        // The preview is at most 300 letters, whatever the task asked.
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Plain text; keep the paragraphs.\nAt most 300 characters.")
            && str_contains($prompt->prompt, "Text:\nBad text")
            && ! str_contains($prompt->prompt, 'Keep the HTML'));
    }

    public function test_a_field_keeps_its_own_length_but_a_piece_is_free_of_it(): void
    {
        Writer::fake(['Title']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('meta_title', ['meta_title' => 'Old title']))->assertStatus(202);
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('meta_title', ['meta_title' => 'Old title'], ['selection' => 'Old']))->assertStatus(202);

        // The field's 40 letters for the whole text; the piece, which is
        // put back into the middle of it, has no length of its own.
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "At most 40 characters.\n\nText:\nOld title"));
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'piece of a longer text') && ! str_contains($prompt->prompt, 'At most'));
    }

    public function test_the_task_limit_holds_where_the_field_has_none(): void
    {
        $brief = app(Briefs::class)->make(Article::class, 'preview_text', ['preview_text' => 'Text'], improve: true);

        $this->assertSame(300, $brief->max);
        $this->assertSame(Task::Improve, $brief->task);
        $this->assertFalse($brief->fragment);
    }

    public function test_a_field_without_any_limit_is_improved_without_one(): void
    {
        $brief = app(Briefs::class)->make(Article::class, 'detail_text', ['detail_text' => 'Text'], improve: true);

        $this->assertNull($brief->max);
        $this->assertSame(['Description' => 'Text'], $brief->sources);
    }

    public function test_a_piece_is_a_fragment_and_has_no_limit(): void
    {
        $brief = app(Briefs::class)->make(Article::class, 'preview_text', ['preview_text' => 'Text is long'], improve: true, selection: 'Text');

        $this->assertTrue($brief->fragment);
        $this->assertNull($brief->max);
        $this->assertSame(['Preview' => 'Text'], $brief->sources);
    }

    public function test_the_text_in_the_language_being_edited_is_the_one_improved(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Ein Hotel am See.']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('preview_text', ['preview_text' => ['en' => 'A hotel', 'de' => 'Ein Hotel am Se']], ['locale' => 'de']))
            ->assertJsonPath('value', 'Ein Hotel am See.');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Text:\nEin Hotel am Se") && ! str_contains($prompt->prompt, 'A hotel'));
    }

    public function test_another_language_is_no_substitute_for_the_text(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Text'])->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('preview_text', ['preview_text' => ['en' => 'A hotel', 'de' => '']], ['locale' => 'de']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.no_source'));

        Writer::assertNeverPrompted();
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:?string}>
     */
    public static function empty(): array
    {
        return [
            'no value at all' => [[], null],
            'a null' => [['detail_text' => null], null],
            'an empty string' => [['detail_text' => ''], null],
            'spaces' => [['detail_text' => "  \n  "], null],
            'an empty paragraph' => [['detail_text' => '<p></p>'], null],
            'tags and nothing between them' => [['detail_text' => '<p> <br> </p><div></div>'], null],
            'the other fields of the form are no text' => [['name' => 'Lake', 'preview_text' => 'Rooms'], null],
            'a piece that is only tags' => [['detail_text' => '<p>Text</p>'], '<p></p>'],
        ];
    }

    /**
     * @param  array<string,mixed>  $values
     */
    #[DataProvider('empty')]
    public function test_there_is_nothing_to_improve_in_an_empty_field(array $values, ?string $selection): void
    {
        Writer::fake(['Text'])->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', $values, ['selection' => $selection]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.no_source'));

        Writer::assertNeverPrompted();
    }

    public function test_a_text_that_does_not_fit_is_refused_not_cut(): void
    {
        config(['atrium-ai.max_source_length' => 100]);
        Writer::fake(['Text'])->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.too_long'));

        // The piece is measured, not the text around it.
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => 'Short'], ['selection' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.too_long'));

        Writer::assertNeverPrompted();
    }

    public function test_a_text_exactly_as_long_as_allowed_goes_through_whole(): void
    {
        config(['atrium-ai.max_source_length' => 100]);
        Writer::fake(['Text']);
        $text = str_repeat('a', 100);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => $text]))->assertStatus(202);
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => 'x'], ['selection' => $text]))->assertStatus(202);

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, "Text:\n{$text}"));
    }

    public function test_a_text_is_measured_with_its_tags(): void
    {
        config(['atrium-ai.max_source_length' => 100]);
        Writer::fake(['Text'])->preventStrayPrompts();

        // Fifty letters of text, but the HTML that has to come back whole is longer.
        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => '<p>'.str_repeat('<b>a</b>', 30).'</p>']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.too_long'));
    }

    /**
     * The HTTP layer trims what it gets, so the brief is asked for directly where
     * the spaces around a text matter.
     */
    public function test_spaces_around_a_text_do_not_count_towards_its_length(): void
    {
        config(['atrium-ai.max_source_length' => 100]);
        $briefs = app(Briefs::class);

        $whole = $briefs->make(Article::class, 'detail_text', ['detail_text' => '   '.str_repeat('a', 100).'   '], improve: true);
        $piece = $briefs->make(Article::class, 'detail_text', ['detail_text' => 'x'], improve: true, selection: "\n ".str_repeat('a', 100)." \n");

        $this->assertSame(str_repeat('a', 100), $whole->sources['Description'] ?? null);
        $this->assertSame(str_repeat('a', 100), $piece->sources['Description'] ?? null);
    }

    public function test_a_text_is_measured_in_letters_not_bytes(): void
    {
        config(['atrium-ai.max_source_length' => 100]);
        $briefs = app(Briefs::class);

        // A hundred letters of two bytes each: allowed.
        $brief = $briefs->make(Article::class, 'detail_text', ['detail_text' => str_repeat('я', 100)], improve: true);
        $this->assertSame(str_repeat('я', 100), $brief->sources['Description'] ?? null);

        // A hundred and one is not.
        $this->expectException(ValidationException::class);
        $briefs->make(Article::class, 'detail_text', ['detail_text' => str_repeat('я', 101)], improve: true);
    }

    public function test_a_selection_of_only_spaces_falls_back_to_the_whole_text(): void
    {
        $brief = app(Briefs::class)->make(Article::class, 'detail_text', ['detail_text' => 'The whole text'], improve: true, selection: "  \n ");

        $this->assertFalse($brief->fragment);
        $this->assertSame(['Description' => 'The whole text'], $brief->sources);
    }

    public function test_only_a_picked_piece_is_a_fragment(): void
    {
        $briefs = app(Briefs::class);

        $this->assertTrue($briefs->make(Article::class, 'detail_text', ['detail_text' => 'The whole text'], improve: true, selection: 'whole')->fragment);
        $this->assertFalse($briefs->make(Article::class, 'detail_text', ['detail_text' => 'The whole text'], improve: true)->fragment);
        // Writing a text is no fragment either.
        $this->assertFalse($briefs->make(Article::class, 'detail_text', ['name' => 'Spring'])->fragment);
    }

    public function test_improving_and_translating_at_once_is_refused(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
        Writer::fake(['Text'])->preventStrayPrompts();

        try {
            app(Briefs::class)->make(Article::class, 'preview_text', ['preview_text' => 'Text'], translate: true, improve: true);
            $this->fail('Two jobs at once should be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(['improve'], array_keys($e->errors()));
            $this->assertSame(__('validation.prohibits', ['attribute' => 'improve', 'other' => 'translate']), $e->errors()['improve'][0]);
            $this->assertStringContainsString('improve', $e->errors()['improve'][0]);
        }

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('preview_text', ['preview_text' => 'Text'], ['translate' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('improve');

        Writer::assertNeverPrompted();
    }

    public function test_only_fields_with_ai_are_improved(): void
    {
        Writer::fake(['Text'])->preventStrayPrompts();

        foreach (['code', 'name', 'picture', 'nope'] as $field) {
            $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body($field, [$field => 'Some text']))
                ->assertUnprocessable()
                ->assertJsonPath('errors.field.0', __('atrium-ai::ai.errors.no_ai'));
        }

        Writer::assertNeverPrompted();
    }

    public function test_it_takes_the_right_to_edit_the_module(): void
    {
        Writer::fake(['Text'])->preventStrayPrompts();

        $this->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => 'Text']))->assertUnauthorized();
        $this->actingAs($this->editor(['articles.view']), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => 'Text']))->assertForbidden();

        Writer::assertNeverPrompted();
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function invalid(): array
    {
        return [
            'an improve that is not a flag' => [['improve' => 'maybe'], 'improve'],
            'a selection that is not a text' => [['selection' => ['a']], 'selection'],
            'a selection too long to be a piece of anything' => [['selection' => str_repeat('a', 100001)], 'selection'],
        ];
    }

    /**
     * @param  array<string,mixed>  $extra
     */
    #[DataProvider('invalid')]
    public function test_a_request_that_is_not_in_order_is_refused_before_anything_is_asked(array $extra, string $field): void
    {
        Writer::fake()->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $this->body('detail_text', ['detail_text' => 'Text'], $extra))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        Writer::assertNeverPrompted();
    }

    public function test_what_is_left_out_may_be_null(): void
    {
        Writer::fake(['Text']);

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake'], 'improve' => null, 'selection' => null])
            ->assertStatus(202)
            ->assertJsonMissingValidationErrors(['improve', 'selection']);
    }

    public function test_the_task_is_offered_with_the_others(): void
    {
        $page = $this->actingAs($this->admin(), 'admin')->get('/admin/articles/create')->viewData('page');

        $this->assertContains('improve', $page['props']['ai']['tasks']);
        $this->assertSame('Improve', $page['props']['ai']['labels']['improve']);
    }
}
