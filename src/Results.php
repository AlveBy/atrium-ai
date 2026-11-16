<?php

declare(strict_types=1);

namespace Atrium\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Where a queued generation leaves its answer until the editor's page picks
 * it up. In the cache, for a few minutes, and only for whoever asked.
 */
final class Results
{
    public function start(?int $owner): string
    {
        $id = (string) Str::uuid();
        $this->put($id, ['owner' => $owner, 'status' => 'pending']);

        return $id;
    }

    /**
     * @return array<string,mixed>|null null when unknown, expired or someone else's
     */
    public function find(string $id, ?int $owner): ?array
    {
        $entry = Cache::get($this->key($id));

        if (! is_array($entry) || ($entry['owner'] ?? null) !== $owner) {
            return null;
        }

        unset($entry['owner']);

        return $entry;
    }

    public function done(string $id, Result $result): void
    {
        $this->update($id, ['status' => 'done', ...$result->toArray()]);
    }

    public function failed(string $id, string $message): void
    {
        $this->update($id, ['status' => 'failed', 'message' => $message]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function update(string $id, array $data): void
    {
        $entry = Cache::get($this->key($id));

        if (is_array($entry)) {
            $this->put($id, ['owner' => $entry['owner'] ?? null, ...$data]);
        }
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function put(string $id, array $entry): void
    {
        Cache::put($this->key($id), $entry, (int) config('atrium-ai.result_ttl', 600));
    }

    private function key(string $id): string
    {
        return 'atrium-ai:result:'.$id;
    }
}
