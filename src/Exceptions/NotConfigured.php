<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Exceptions;

use RuntimeException;

/**
 * No provider of a chain has a key.
 */
final class NotConfigured extends RuntimeException
{
    public static function for(string $kind): self
    {
        return new self("No AI provider with a key for \"{$kind}\": set one in .env (see config/atrium-ai.php).");
    }
}
