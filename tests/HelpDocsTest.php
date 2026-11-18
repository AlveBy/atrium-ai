<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Help\Docs;
use Atrium\Ai\Help\Tools\SearchDocs;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The guides the helper answers from: cut into sections by their headings,
 * ranked by the words of the question, in the admin's language where there
 * is a translation.
 */
final class HelpDocsTest extends HelpTestCase
{
    public function test_the_best_section_comes_with_its_guide_title_and_heading(): void
    {
        $english = $this->use(new SearchDocs('en'), ['query' => 'add menu item']);

        $this->assertStringStartsWith("[docs/guide.md] Menus — Add a menu item\n\nOpen Settings, then Menus, and press Add item.\n\n---\n\n[docs/guide.md] Menus — Remove a menu item", $english);
        $this->assertStringNotContainsString('backup', $english);
    }

    public function test_the_tool_tells_the_model_what_it_does_and_what_it_takes(): void
    {
        $this->assertSame(['arguments' => ['query' => 'string'], 'required' => ['query']], $this->described(new SearchDocs('en')));
    }

    public function test_the_three_best_sections_are_given(): void
    {
        $this->guides(['guide.md' => "# Guide\n## One\nzebra\n## Two\nzebra\n## Three\nzebra\n## Four\nzebra"]);

        $this->assertCount(3, app(Docs::class)->search('zebra', 'en'));
        $this->assertSame(3, substr_count($this->use(new SearchDocs('en'), ['query' => 'zebra']), '[docs-guides/guide.md]'));
    }

    public function test_the_title_and_the_heading_are_two_words_not_one(): void
    {
        $this->guides(['guide.md' => "# Guide\n## Zebra\nStripes."]);

        $this->assertSame([], $this->found('dezeb', 'en'));
        $this->assertSame(['Zebra'], $this->found('guide zebra', 'en'));
    }

    public function test_a_heading_that_is_used_twice_is_listed_once(): void
    {
        $this->guides(['guide.md' => "# Guide\n## Same\nOne.\n## Same\nTwo.\n## Other\nThree."]);

        $this->assertSame(['Guide — Same', 'Guide — Other'], app(Docs::class)->headings('en'));
    }

    public function test_a_name_with_a_language_code_inside_it_is_still_a_guide(): void
    {
        $this->guides(['notes.de.md.old.md' => "# Notes\n## Zebra\nzebra"]);

        $this->assertSame(['Zebra'], $this->found('zebra', 'en'));
    }

    public function test_when_nothing_matches_the_model_is_shown_every_section_once(): void
    {
        $this->assertSame(
            "Nothing matched. The sections are:\nMenus — Add a menu item\nMenus — Remove a menu item\nSomething else — \nBackups — Make a backup",
            $this->use(new SearchDocs('en'), ['query' => 'zebra']),
        );
    }

    public function test_a_translated_guide_is_read_in_place_of_the_english_one_and_the_rest_stay_english(): void
    {
        $russian = $this->use(new SearchDocs('ru'), ['query' => 'меню']);

        $this->assertStringStartsWith('[docs/guide.md] Меню — Добавить пункт меню (menu)', $russian);
        $this->assertStringContainsString('backup:run', $this->use(new SearchDocs('ru'), ['query' => 'backup']));
        $this->assertSame([], app(Docs::class)->search('press', 'ru'));
    }

    public function test_a_translation_is_never_a_guide_of_its_own(): void
    {
        $this->guides([
            'guide.md' => "# Guide\n\nzebra in English.",
            'guide.de.md' => "# Anleitung\n\nzebra auf Deutsch.",
            'guide.pt_BR.md' => "# Guia\n\nzebra em português.",
        ]);

        $this->assertSame(['English.'], $this->found('zebra', 'en', field: 'text', strip: 'zebra in '));
        $this->assertSame(['Deutsch.'], $this->found('zebra', 'de', field: 'text', strip: 'zebra auf '));
        $this->assertSame(['português.'], $this->found('zebra', 'pt_BR', field: 'text', strip: 'zebra em '));
        $this->assertSame(['English.'], $this->found('zebra', 'fr', field: 'text', strip: 'zebra in '));
    }

    public function test_a_heading_counts_three_times_a_word_of_the_text_and_one_word_at_most_five_times(): void
    {
        $this->guides(['guide.md' => implode("\n", [
            '# Guide',
            '## Often',
            str_repeat('zebra ', 10),
            '## Once',
            'zebra zebra',
            '## Zebra',
            'zebra zebra zebra',
            '## Zebra too',
            'zebra zebra',
        ])]);

        // 3 + 3, then 5 (ten times in the text), then 3 + 2 (the same score: the order of the guide).
        $this->assertSame(['Zebra', 'Often', 'Zebra too'], $this->found('zebra', 'en'));
        $this->assertSame(['Zebra', 'Often', 'Zebra too', 'Once'], $this->found('zebra', 'en', limit: 10));
    }

    public function test_each_word_of_the_question_counts_once(): void
    {
        $this->guides(['guide.md' => "# Guide\n## First\nzebra\n## Second\nquokka quokka"]);

        $this->assertSame(['Second', 'First'], $this->found('zebra zebra Zebra quokka', 'en'));
    }

    public function test_the_words_are_matched_whatever_their_case_and_script(): void
    {
        $this->guides(['guide.md' => "# Руководство\n## ЗАПИСИ\nКак найти ЗАПИСЬ."]);

        $this->assertSame(['ЗАПИСИ'], $this->found('записи', 'en'));
        $this->assertSame(['ЗАПИСИ'], $this->found('ЗАПИСЬ', 'en'));
        $this->assertSame(['ЗАПИСИ'], $this->found('Руководство', 'en'));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function noWords(): array
    {
        return ['nothing' => [''], 'short words' => ['an is to'], 'punctuation' => ['?! — ...']];
    }

    #[DataProvider('noWords')]
    public function test_a_question_without_a_word_of_three_letters_finds_nothing(string $query): void
    {
        $this->guides(['guide.md' => "# Guide\n## An is to\nan is to ?! — ..."]);

        $this->assertSame([], app(Docs::class)->search($query, 'en'));
    }

    public function test_only_sections_with_a_word_of_the_question_are_returned(): void
    {
        $this->assertSame(['Make a backup'], $this->found('backup', 'en'));
        $this->assertSame(['Add a menu item', 'Remove a menu item'], $this->found('item', 'en'));
    }

    public function test_the_sections_are_cut_at_the_first_three_levels_of_headings(): void
    {
        $this->guides(['guide.md' => implode("\r\n", [
            'Before any heading zebra.',
            '#   Title  ',
            'Under the title zebra.',
            '## Second',
            '### Third   ',
            'Third level zebra.',
            '#### Fourth',
            'Fourth level zebra.',
            '#Not a heading zebra',
            '## Empty',
            '',
            '# Next',
            'Next title zebra.',
        ])]);

        $sections = app(Docs::class)->search('zebra', 'en', 10);

        $this->assertSame([
            // Three times "zebra": first. The rest once each, in the order of the guide.
            ['Title', 'Third', "Third level zebra.\n#### Fourth\nFourth level zebra.\n#Not a heading zebra"],
            ['docs-guides/guide.md', '', 'Before any heading zebra.'],
            ['Title', '', 'Under the title zebra.'],
            ['Next', '', 'Next title zebra.'],
        ], array_map(static fn (array $section): array => [$section['title'], $section['heading'], $section['text']], $sections));
        $this->assertSame('docs-guides/guide.md', $sections[1]['file']);
    }

    public function test_a_long_section_is_cut_at_four_thousand_characters_not_bytes(): void
    {
        $this->guides(['guide.md' => "# Guide\n## Long\nzebra ".str_repeat('я', 4100)]);

        $text = app(Docs::class)->search('zebra', 'en')[0]['text'];

        $this->assertSame(4000, mb_strlen($text));
        $this->assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    /**
     * @return array<string,array{0:\Closure(string):mixed}>
     */
    public static function places(): array
    {
        return [
            'a folder' => [static fn (string $dir): array => [$dir]],
            'a folder with a slash' => [static fn (string $dir): array => [$dir.'/']],
            'a pattern' => [static fn (string $dir): array => [$dir.'/*.md']],
            'one file' => [static fn (string $dir): array => [$dir.'/guide.md']],
            'a folder not in a list' => [static fn (string $dir): string => $dir],
        ];
    }

    /**
     * @param  \Closure(string):mixed  $place
     */
    #[DataProvider('places')]
    public function test_the_guides_are_found_in_a_folder_by_a_pattern_or_by_name(\Closure $place): void
    {
        $dir = $this->guides(['guide.md' => "# Guide\n## Zebra\nzebra"]);
        config(['atrium-ai.help.docs' => $place($dir)]);

        $this->assertSame(['Zebra'], $this->found('zebra', 'en'));
    }

    public function test_a_folder_finds_only_markdown_and_a_missing_place_finds_nothing(): void
    {
        $dir = $this->guides(['guide.txt' => "# Guide\n## Zebra\nzebra"]);
        config(['atrium-ai.help.docs' => [$dir, $dir.'/missing', $dir.'/missing/*.md']]);

        $this->assertSame([], $this->found('zebra', 'en'));
        $this->assertSame([], app(Docs::class)->headings('en'));
    }

    public function test_without_guides_there_is_nothing_to_search(): void
    {
        $this->forgetConfig('atrium-ai.help.docs');

        $this->assertSame([], app(Docs::class)->search('menu', 'en'));
        $this->assertSame("Nothing matched. The sections are:\n", $this->use(new SearchDocs('en'), ['query' => 'menu']));
    }

    public function test_guides_of_the_same_name_in_two_folders_are_told_apart(): void
    {
        $first = $this->guides(['README.md' => "# Catalog\n## Zebra\nzebra"], 'catalog');
        $second = $this->guides(['README.md' => "# Blog\n## Zebra\nzebra"], 'blog');
        config(['atrium-ai.help.docs' => [$first, $second]]);

        $this->assertSame(['catalog/README.md', 'blog/README.md'], $this->found('zebra', 'en', field: 'file'));
    }

    /**
     * Puts the guides into a folder of their own and makes it the only one searched.
     *
     * @param  array<string,string>  $files
     */
    private function guides(array $files, string $name = 'docs-guides'): string
    {
        $dir = sys_get_temp_dir().'/'.uniqid('atrium-ai-', true).'/'.$name;
        File::ensureDirectoryExists($dir);

        foreach ($files as $file => $text) {
            File::put($dir.'/'.$file, $text);
        }

        config(['atrium-ai.help.docs' => [$dir]]);
        $this->beforeApplicationDestroyed(static fn () => File::deleteDirectory(dirname($dir)));

        return $dir;
    }

    /**
     * @return list<string>
     */
    private function found(string $query, string $locale, int $limit = 3, string $field = 'heading', string $strip = ''): array
    {
        return array_map(
            static fn (array $section): string => str_replace($strip, '', $section[$field]),
            app(Docs::class)->search($query, $locale, $limit),
        );
    }
}
