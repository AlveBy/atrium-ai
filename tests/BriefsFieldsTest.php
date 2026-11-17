<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Ai\Brief;
use Atrium\Ai\Briefs;
use Atrium\Ai\Task;
use Atrium\Ai\Tests\Fixtures\Plain;
use Atrium\Ai\Tests\Fixtures\Rich;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a brief reads from the form: which fields are sources, how a value is
 * cleaned, and what the field being written allows.
 */
final class BriefsFieldsTest extends TestCase
{
    /**
     * @param  array<string,mixed>  $values
     */
    private function brief(string $field, array $values, ?string $locale = null, bool $translate = false, string $model = Rich::class): Brief
    {
        return app(Briefs::class)->make($model, $field, $values, $locale, $translate);
    }

    private function german(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->flush();
    }

    public function test_a_content_field_is_written_as_html_and_a_text_field_as_text(): void
    {
        $html = $this->brief('detail_text', ['name' => 'Lake']);
        $text = $this->brief('summary', ['name' => 'Lake']);

        $this->assertSame(['html', null], [$html->format, $html->max]);
        $this->assertSame(['text', 80], [$text->format, $text->max]);
        $this->assertSame(['text', 255], [$this->brief('free', ['name' => 'Lake'])->format, $this->brief('free', ['name' => 'Lake'])->max]);
    }

    public function test_a_length_of_zero_is_no_limit(): void
    {
        $this->assertNull($this->brief('caption', ['name' => 'Lake'])->max);
    }

    public function test_without_named_sources_every_text_field_of_the_form_is_read(): void
    {
        $brief = $this->brief('free', [
            'name' => 'Lake', 'code' => 'lake', 'email' => 'a@example.com', 'password' => 'pw', 'url' => 'https://example.com',
            'phone' => '123', 'summary' => 'Short', 'detail_text' => '<b>Long</b>', 'body' => '<p>Body</p>', 'qty' => '5',
            'picture' => '/a.png', 'free' => 'Itself',
        ]);

        $this->assertSame(['Name' => 'Lake', 'Summary' => 'Short', 'Detail' => 'Long', 'Body' => 'Body'], $brief->sources);
    }

    public function test_named_sources_skip_the_field_itself_and_the_ones_the_form_does_not_have(): void
    {
        $brief = $this->brief('notes', ['name' => 'Lake', 'notes' => 'Mine', 'detail_text' => 'Long', 'summary' => 'Not named']);

        $this->assertSame(['Name' => 'Lake', 'Detail' => 'Long'], $brief->sources);
    }

    public function test_when_the_named_sources_are_empty_the_rest_of_the_form_is_read(): void
    {
        $brief = $this->brief('summary', ['name' => 'Lake', 'detail_text' => '']);

        $this->assertSame(['Name' => 'Lake'], $brief->sources);
    }

    /**
     * @return array<string,array{0:mixed,1:string}>
     */
    public static function values(): array
    {
        return [
            'line breaks are kept' => ['A<br>B<br/>C<br />D<p>E</p><ul><li>F</li><li>G</li></ul>', "A\nB\nC\nDE\nF\nG"],
            'spaces are squeezed and blank lines become one' => ["A   B\t\tC\n\n\n\nD  ", "A B C\n\nD"],
            'entities are decoded' => ['Tom &amp; Jerry &quot;x&quot; &apos;y&apos;', 'Tom & Jerry "x" \'y\''],
            'a number is a text' => [2026, '2026'],
            'a numeric string is a text' => ['2026', '2026'],
        ];
    }

    #[DataProvider('values')]
    public function test_a_value_is_read_as_plain_text(mixed $value, string $expected): void
    {
        $this->assertSame(['Summary' => $expected], $this->brief('free', ['summary' => $value])->sources);
    }

    public function test_a_value_that_is_not_valid_utf8_is_nothing(): void
    {
        $this->assertSame(['Name' => 'Lake'], $this->brief('free', ['name' => 'Lake', 'summary' => "bad \xB1 text"])->sources);
    }

    public function test_a_long_text_is_cut_by_letters_not_by_bytes(): void
    {
        config(['atrium-ai.max_source_length' => 100]);

        $this->assertSame(['Name' => str_repeat('ж', 100)], $this->brief('free', ['name' => str_repeat('ж', 150)])->sources);
    }

    public function test_a_value_that_is_neither_text_nor_a_number_is_nothing(): void
    {
        $this->assertSame(['Name' => 'Lake'], $this->brief('free', ['name' => 'Lake', 'summary' => true])->sources);
    }

    public function test_a_translatable_value_is_read_in_the_language_edited(): void
    {
        $this->german();

        $this->assertSame(['Name' => 'See'], $this->brief('free', ['name' => ['en' => 'Lake', 'de' => 'See']], 'de')->sources);
        $this->assertSame(['Name' => 'Lake'], $this->brief('free', ['name' => ['en' => 'Lake']], 'de')->sources);
        $this->assertSame(['Name' => 'English'], $this->brief('free', ['name' => ['fr' => 'Texte', 'en' => 'English']], 'de')->sources);
        $this->assertSame(['Name' => 'Testo'], $this->brief('free', ['name' => ['fr' => '  ', 'es' => ['x'], 'it' => 'Testo']], 'de')->sources);
    }

    public function test_the_text_to_translate_is_the_one_in_the_language_edited(): void
    {
        $this->german();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('atrium-ai::ai.errors.no_source'));

        $this->brief('detail_text', ['detail_text' => ['en' => '<p>Hello</p>']], 'de', true);
    }

    public function test_html_is_kept_in_the_text_to_translate_of_a_html_field(): void
    {
        $this->german();

        $brief = $this->brief('detail_text', ['detail_text' => ['en' => '<p>Hello</p>']], 'en', true);

        $this->assertSame(['Detail' => '<p>Hello</p>'], $brief->sources);
        $this->assertSame(Task::Translate, $brief->task);
    }

    public function test_a_field_the_model_does_not_translate_is_refused(): void
    {
        $this->german();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('atrium-ai::ai.errors.not_translatable'));

        $this->brief('summary', ['summary' => ['en' => 'Hello']], 'en', true);
    }

    public function test_a_model_that_is_not_translatable_is_refused_not_broken(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('atrium-ai::ai.errors.not_translatable'));

        $this->brief('name', ['name' => 'Hello'], 'en', true, Plain::class);
    }

    public function test_a_source_is_cut_to_the_length_the_config_allows_but_never_under_a_hundred(): void
    {
        config(['atrium-ai.max_source_length' => 500]);
        $this->assertSame(500, mb_strlen($this->brief('free', ['name' => str_repeat('x', 800)])->sources['Name']));

        config(['atrium-ai.max_source_length' => 50]);
        $this->assertSame(100, mb_strlen($this->brief('free', ['name' => str_repeat('x', 800)])->sources['Name']));

        $this->forgetConfig('atrium-ai.max_source_length');
        $this->assertSame(6000, mb_strlen($this->brief('free', ['name' => str_repeat('x', 7000)])->sources['Name']));
    }

    public function test_the_value_of_a_picture_field_is_read_as_plain_text(): void
    {
        $file = public_path('storage/ai-brief/lake.png');
        @mkdir(dirname($file), 0777, true);
        file_put_contents($file, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        try {
            $brief = $this->brief('picture_alt', ['picture' => '<i>/storage/ai-brief/lake.png</i>', 'name' => 'Lake']);

            $this->assertSame(realpath($file), $brief->image);
            $this->assertSame(['Name' => 'Lake'], $brief->sources);
        } finally {
            @unlink($file);
            @rmdir(dirname($file));
        }
    }
}
