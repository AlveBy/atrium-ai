<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Help;

/**
 * The Atrium guides and the module READMEs, cut into sections by their
 * headings, for the helper's search_docs tool. A guide in the admin's
 * language (configuration.ru.md) stands in for the English one.
 */
final class Docs
{
    /**
     * The sections that best match the words of a query.
     *
     * @return list<array{file:string,title:string,heading:string,text:string}>
     */
    public function search(string $query, string $locale, int $limit = 3): array
    {
        $words = $this->words($query);
        $sections = $this->sections($locale);
        $scored = [];

        foreach ($sections as $index => $section) {
            $heading = mb_strtolower($section['title'].' '.$section['heading']);
            $text = mb_strtolower($section['text']);
            $score = 0;

            foreach ($words as $word) {
                $score += 3 * substr_count($heading, $word) + min(5, substr_count($text, $word));
            }

            if ($score > 0) {
                $scored[$index] = $score;
            }
        }

        arsort($scored);

        return array_map(static fn (int $index): array => $sections[$index], array_slice(array_keys($scored), 0, $limit));
    }

    /**
     * "Guide — section" of every section, to show when nothing matched.
     *
     * @return list<string>
     */
    public function headings(string $locale): array
    {
        return array_values(array_unique(array_map(
            static fn (array $section): string => $section['title'].' — '.$section['heading'],
            $this->sections($locale),
        )));
    }

    /**
     * @return list<array{file:string,title:string,heading:string,text:string}>
     */
    private function sections(string $locale): array
    {
        $sections = [];

        foreach ($this->files($locale) as $name => $path) {
            $title = $name;
            $heading = '';
            $lines = [];
            $flush = static function () use (&$sections, &$lines, &$heading, &$title, $name): void {
                $text = trim(implode("\n", $lines));

                if ($text !== '') {
                    $sections[] = ['file' => $name, 'title' => $title, 'heading' => $heading, 'text' => mb_substr($text, 0, 4000)];
                }

                $lines = [];
            };

            foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
                if (preg_match('/^(#{1,3})\s+(.+)/', $line, $match)) {
                    $flush();

                    if ($match[1] === '#') {
                        $title = trim($match[2]);
                        $heading = '';
                    } else {
                        $heading = trim($match[2]);
                    }

                    continue;
                }

                $lines[] = $line;
            }

            $flush();
        }

        return $sections;
    }

    /**
     * One file per guide: the admin's language if there is one, else English.
     *
     * @return array<string,string> name shown to the model => path
     */
    private function files(string $locale): array
    {
        $files = [];

        foreach ((array) config('atrium-ai.help.docs', []) as $pattern) {
            $paths = is_dir((string) $pattern) ? glob($pattern.'/*.md') : glob((string) $pattern);

            foreach ($paths ?: [] as $path) {
                // A guide's translation is read in place of the guide itself.
                if (preg_match('/\.[a-z]{2}(_[A-Z]{2})?\.md$/', $path)) {
                    continue;
                }

                $own = substr($path, 0, -3).'.'.$locale.'.md';
                $files[$this->name($path)] = is_file($own) ? $own : $path;
            }
        }

        return $files;
    }

    /** "docs/modules.md", "catalog/README.md": enough to tell them apart. */
    private function name(string $path): string
    {
        return basename(dirname($path)).'/'.basename($path);
    }

    /**
     * @return array<int,string>
     */
    private function words(string $query): array
    {
        preg_match_all('/[\p{L}\p{N}_]{3,}/u', mb_strtolower($query), $matches);

        return array_unique($matches[0]);
    }
}
