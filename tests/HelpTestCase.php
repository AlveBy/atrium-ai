<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Ai\Tests\Fixtures\Article;
use Atrium\Ai\Tests\Fixtures\Store;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The helper in the corner of the admin: two modules (one of them kept per
 * site) and a few guides to search.
 */
abstract class HelpTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('atrium.resources', [Article::class, Store::class]);
        $app['config']->set('atrium-ai.help.docs', [__DIR__.'/Fixtures/docs']);
    }

    /**
     * Runs a tool the way the model would, after checking its schema builds.
     *
     * @param  array<string,mixed>  $arguments
     */
    protected function use(Tool $tool, array $arguments = []): string
    {
        $this->assertIsArray($tool->schema(new JsonSchemaTypeFactory));

        return (string) $tool->handle(new Request($arguments));
    }

    /**
     * What the model is told of a tool: that it does something, the type of
     * each argument (with its choices) and which arguments it must give.
     *
     * @return array{arguments:array<string,string>,required:list<string>}
     */
    protected function described(Tool $tool): array
    {
        $schema = (new JsonSchemaTypeFactory)->object($tool->schema(new JsonSchemaTypeFactory))->toArray();
        $this->assertNotSame('', trim((string) $tool->description()));

        return [
            'arguments' => array_map(static fn (array $argument): string => $argument['type'].(isset($argument['enum']) ? ':'.implode('|', $argument['enum']) : ''), $schema['properties'] ?? []),
            'required' => $schema['required'] ?? [],
        ];
    }

    /** An administrator with no role at all. */
    protected function stranger(): AdminUser
    {
        return AdminUser::query()->create([
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
    }

    protected function mainSite(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    protected function secondSite(): Site
    {
        return Site::query()->create([
            'code' => 'second',
            'name' => 'Second shop',
            'domains' => ['second.example.com'],
            'languages' => ['en'],
        ]);
    }
}
