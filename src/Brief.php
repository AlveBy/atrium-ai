<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

/**
 * Everything one generation needs, already read off the form: it travels to
 * the queued job, so it holds plain values only.
 */
final readonly class Brief
{
    /**
     * @param  string  $language  code of the language to answer in
     * @param  array<string,string>  $sources  field label => its text, cleaned and cut
     * @param  string  $field  label of the field being filled
     * @param  string  $format  "text" or "html"
     * @param  int|null  $max  hard length of the field (TextField::maxLength)
     * @param  string|null  $image  absolute path of a local picture, for "alt"
     * @param  array<string,string>  $targets  "translate": language code => name
     * @param  bool  $fragment  "improve": the source is a piece picked out of a longer text
     */
    public function __construct(
        public Task $task,
        public string $language,
        public array $sources,
        public string $field,
        public string $format = 'text',
        public ?int $max = null,
        public ?string $image = null,
        public array $targets = [],
        public ?string $module = null,
        public ?int $user = null,
        public bool $fragment = false,
    ) {}

    /** How many times the provider is asked: once, or once per language of a translation. */
    public function calls(): int
    {
        return $this->task === Task::Translate ? count($this->targets) : 1;
    }
}
