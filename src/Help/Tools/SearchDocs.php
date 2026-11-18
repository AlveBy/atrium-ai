<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Help\Tools;

use Atrium\Ai\Help\Docs;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SearchDocs implements Tool
{
    public function __construct(private readonly string $locale) {}

    public function name(): string
    {
        return 'search_docs';
    }

    public function description(): string
    {
        return 'Search the Atrium guides and module READMEs: how to set up, use and configure the panel and its modules. Returns the best matching sections.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('A few keywords. The guides are in English (some also in Russian), so add English keywords.')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $docs = app(Docs::class);
        $found = $docs->search((string) $request->string('query'), $this->locale);

        if ($found === []) {
            return "Nothing matched. The sections are:\n".implode("\n", $docs->headings($this->locale));
        }

        return implode("\n\n---\n\n", array_map(
            static fn (array $section): string => "[{$section['file']}] {$section['title']} — {$section['heading']}\n\n{$section['text']}",
            $found,
        ));
    }
}
