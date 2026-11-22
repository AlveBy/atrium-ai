<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The daily ceiling of a public demo (docs/demo.md): every visitor signs in
 * as the same administrator, so a per-minute throttle cannot tell one of them
 * from the others and does not stop one visitor from using the provider's
 * whole free quota for the day. While `atrium.demo.enabled` is on, the site
 * as a whole gets `atrium-ai.demo.daily_limit` provider calls a day; outside
 * a demo nothing is counted.
 *
 * The count is in a cache store of its own ("file" by default), not in the
 * ai_usage table: the demo's reset empties the database every hour, the usage
 * log and a database cache with it, which would give the visitors a fresh
 * allowance each hour.
 */
final class DemoLimit
{
    /**
     * Takes the calls a request is about to make out of today's allowance.
     * One question or one text is one call; a translation asks once per
     * language. Nothing is taken, and the request is refused with 429, when
     * the allowance does not cover it, so that no provider is asked.
     */
    public function claim(int $calls = 1): void
    {
        $limit = $this->limit();

        if ($limit === null) {
            return;
        }

        $key = $this->key();
        $store = $this->store();
        $store->add($key, 0, now()->endOfDay()->addHour());

        if ($store->increment($key, $calls) > $limit) {
            $store->decrement($key, $calls);

            abort(429, __('atrium-ai::ai.errors.demo_limit'), ['Retry-After' => (string) max(1, (int) now()->diffInSeconds(now()->endOfDay()))]);
        }
    }

    /**
     * Whether the buttons and the helper should tell visitors that the demo
     * is public.
     */
    public function active(): bool
    {
        return (bool) config('atrium.demo.enabled');
    }

    /** The calls a day, or null when nothing is counted. */
    private function limit(): ?int
    {
        $limit = (int) config('atrium-ai.demo.daily_limit', 40);

        return $this->active() && $limit > 0 ? $limit : null;
    }

    private function key(): string
    {
        return 'atrium-ai:demo-calls:'.now()->toDateString();
    }

    private function store(): Repository
    {
        return Cache::store(config('atrium-ai.demo.store', 'file'));
    }
}
