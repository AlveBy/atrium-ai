<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Illuminate\Contracts\Support\Arrayable;

/**
 * What a generation produced: one text, or one per language for
 * "translate". Offered to the editor, never saved by itself.
 *
 * @implements Arrayable<string,mixed>
 */
final readonly class Result implements Arrayable
{
    /**
     * @param  array<string,string>|null  $values  language code => text
     */
    public function __construct(
        public ?string $value = null,
        public ?array $values = null,
        public ?string $provider = null,
        public ?string $model = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'value' => $this->value,
            'values' => $this->values,
            'provider' => $this->provider,
            'model' => $this->model,
        ], static fn ($value): bool => $value !== null);
    }
}
