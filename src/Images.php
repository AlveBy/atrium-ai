<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Illuminate\Support\Facades\Storage;

/**
 * The file behind an image field's value, for the "alt" task. Only pictures
 * that lie on this server: a value is never fetched from another host, so the
 * form cannot be used to make the server download anything.
 */
final class Images
{
    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function local(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            $host = parse_url($value, PHP_URL_HOST);
            $own = [parse_url((string) config('app.url'), PHP_URL_HOST), request()->getHost()];

            if (! is_string($host) || ! in_array(strtolower($host), array_map('strtolower', array_filter($own)), true)) {
                return null;
            }

            $value = (string) parse_url($value, PHP_URL_PATH);
        }

        $path = rawurldecode((string) strtok($value, '?#'));

        if (in_array('..', preg_split('#[/\\\\]+#', $path) ?: [], true)) {
            return null;
        }

        // "/storage/uploads/x.jpg", "/upload/iblock/x.jpg" — from the web root;
        // "uploads/x.jpg" — on the public disk.
        $candidate = str_starts_with($path, '/')
            ? public_path(ltrim($path, '/'))
            : Storage::disk('public')->path($path);

        return $this->safe($candidate);
    }

    private function safe(string $candidate): ?string
    {
        $real = realpath($candidate);

        if ($real === false || ! is_file($real) || ! in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            return null;
        }

        foreach ([public_path(), Storage::disk('public')->path('')] as $root) {
            $root = realpath($root);

            if ($root !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        return null;
    }
}
