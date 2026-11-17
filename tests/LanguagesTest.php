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
use Atrium\Ai\Brief;
use Atrium\Ai\Jobs\Generate;
use Atrium\Ai\LanguageNames;
use Atrium\Ai\Task;
use Illuminate\Support\Arr;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every Atrium language: the module's own strings, and the languages it
 * writes and translates into.
 */
final class LanguagesTest extends TestCase
{
    private const ATRIUM = ['en', 'ru', 'uk', 'be', 'kk', 'uz', 'de', 'fr', 'es', 'pt_BR', 'it', 'pl', 'tr', 'zh_CN', 'ja'];

    /**
     * Data providers run before Laravel boots: the list is read from the
     * admin's config file.
     *
     * @return array<string, array{string, string}>
     */
    public static function locales(): array
    {
        $config = require __DIR__.'/../../admin/config/atrium.php';

        return Arr::mapWithKeys($config['locales'], static fn (string $name, string $code): array => [$code => [$code, $name]]);
    }

    public function test_the_admin_lists_the_fifteen_atrium_languages(): void
    {
        $this->assertSame(self::ATRIUM, array_keys(self::locales()));
    }

    #[DataProvider('locales')]
    public function test_the_language_has_every_string_of_english_and_nothing_else(string $locale, string $name): void
    {
        $english = $this->strings('en');
        $own = $this->strings($locale);

        $this->assertSame([], array_values(array_diff(array_keys($english), array_keys($own))), "Missing in {$locale}");
        $this->assertSame([], array_values(array_diff(array_keys($own), array_keys($english))), "Unknown in {$locale}");

        foreach ($own as $key => $line) {
            $this->assertNotSame('', trim($line), "{$locale}: {$key} is empty");
            $this->assertSame($this->placeholders($english[$key]), $this->placeholders($line), "{$locale}: {$key} placeholders differ from English");
        }
    }

    #[DataProvider('locales')]
    public function test_the_model_is_told_the_language_in_english(string $locale, string $name): void
    {
        $code = $this->content($locale);
        $described = LanguageNames::describe($code, $name);

        $this->assertMatchesRegularExpression('/^[A-Z][a-z]+( [A-Za-z]+)* \(/', $described, $described);
        $this->assertStringEndsWith("{$code})", $described);
    }

    public function test_the_script_is_named_where_a_language_has_more_than_one(): void
    {
        $this->assertSame('Uzbek in Latin script (Oʻzbekcha, uz)', LanguageNames::describe('uz', 'Oʻzbekcha'));
        $this->assertSame('Kazakh in Cyrillic script (Қазақша, kk)', LanguageNames::describe('kk', 'Қазақша'));
        $this->assertSame('Belarusian in Cyrillic script (Беларуская, be)', LanguageNames::describe('be', 'Беларуская'));
        $this->assertSame('Simplified Chinese (简体中文, zh-cn)', LanguageNames::describe('zh-cn', '简体中文'));
        $this->assertSame('Brazilian Portuguese (Português (Brasil), pt-br)', LanguageNames::describe('pt-br', 'Português (Brasil)'));
        $this->assertSame('English (en)', LanguageNames::describe('en', 'English'));
        // A script in the code wins; an unknown language keeps the site's name.
        $this->assertSame('Uzbek in Cyrillic script (Ўзбекча, uz-cyrl)', LanguageNames::describe('uz-cyrl', 'Ўзбекча'));
        $this->assertSame('Kazakh in Latin script (kk-latn)', LanguageNames::describe('kk-latn'));
        $this->assertSame('Traditional Chinese (繁體中文, zh-tw)', LanguageNames::describe('zh-tw', '繁體中文'));
        $this->assertSame('Suomi (fi)', LanguageNames::describe('fi', 'Suomi'));
        $this->assertSame('fi', LanguageNames::describe('fi', ''));
    }

    public function test_the_code_is_read_in_any_case_with_a_dash_or_an_underscore(): void
    {
        $this->assertSame('Uzbek in Cyrillic script (Ўзбекча, UZ_CYRL)', LanguageNames::describe('UZ_CYRL', 'Ўзбекча'));
        $this->assertSame('Uzbek in Cyrillic script (UZ-CYRL)', LanguageNames::describe('UZ-CYRL'));
        $this->assertSame('Brazilian Portuguese (pt_BR)', LanguageNames::describe('pt_BR'));
        // Only the first dash splits the code: what follows is the script.
        $this->assertSame('Uzbek in Latin script (uz-cyrl-x)', LanguageNames::describe('uz-cyrl-x'));
    }

    public function test_the_name_the_site_gave_is_trimmed_and_ignored_when_blank(): void
    {
        $this->assertSame('Suomi (fi)', LanguageNames::describe('fi', '  Suomi  '));
        $this->assertSame('fi', LanguageNames::describe('fi', '   '));
        $this->assertSame('German (Deutsch, de)', LanguageNames::describe('de', '  Deutsch '));
        $this->assertSame('German (de)', LanguageNames::describe('de', '   '));
    }

    public function test_a_field_is_translated_into_each_of_the_other_fourteen_languages(): void
    {
        foreach (self::locales() as [$locale, $name]) {
            Language::query()->updateOrCreate(['code' => $this->content($locale)], ['name' => $name, 'active' => true]);
        }
        app(ContentLanguages::class)->flush();
        Writer::fake(fn (string $prompt): string => 'Translated');

        $values = $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', [
            'module' => 'articles',
            'field' => 'preview_text',
            'locale' => 'en',
            'translate' => true,
            'values' => ['preview_text' => ['en' => 'Lake hotel']],
        ])->assertSuccessful()->json('values');

        $others = array_map($this->content(...), array_slice(self::ATRIUM, 1));
        $this->assertEqualsCanonicalizing($others, array_keys($values));

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'into Uzbek in Latin script (Oʻzbekcha, uz)')
            && str_contains($prompt->agent->instructions(), 'Write in Uzbek in Latin script (Oʻzbekcha, uz)'));
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'into Kazakh in Cyrillic script (Қазақша, kk)'));
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'into Belarusian in Cyrillic script (Беларуская, be)'));
        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'into Japanese (日本語, ja)'));
    }

    public function test_a_translation_into_many_languages_gets_the_time_for_each(): void
    {
        $targets = array_fill_keys(range(1, 14), 'Language');
        $brief = new Brief(Task::Translate, 'en', ['Text' => 'Lake hotel'], field: 'Text', targets: $targets);

        $this->assertSame(15 * 60, (new Generate('id', $brief))->timeout);
        $this->assertSame(300, (new Generate('id', new Brief(Task::Describe, 'en', ['Text' => 'x'], field: 'Text')))->timeout);

        $this->forgetConfig('atrium-ai.timeout');
        $this->assertSame(15 * 60, (new Generate('id', $brief))->timeout);
        config(['atrium-ai.timeout' => 100]);
        $this->assertSame(15 * 100, (new Generate('id', $brief))->timeout);
        // A time from the .env comes as text, or with a decimal point.
        config(['atrium-ai.timeout' => '90.5']);
        $this->assertSame(15 * 90, (new Generate('id', $brief))->timeout);
    }

    /** "pt_BR" of the panel is the content language "pt-br". */
    private function content(string $locale): string
    {
        return str_replace('_', '-', strtolower($locale));
    }

    /**
     * @return array<string, string>
     */
    private function strings(string $locale): array
    {
        $file = __DIR__."/../lang/{$locale}/ai.php";
        $this->assertFileExists($file);

        return Arr::dot(require $file);
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $line): array
    {
        preg_match_all('/:([a-z_]+)/i', $line, $matches);
        $found = array_values(array_unique($matches[1]));
        sort($found);

        return $found;
    }
}
