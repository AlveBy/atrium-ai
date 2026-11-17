<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests\Concerns;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * Tokens of the API and calls of the MCP server, for the tests of what an
 * external program may do.
 */
trait CallsTheApi
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    /**
     * @param  array<int,string>  $permissions
     * @param  array<int,string>  $abilities
     */
    private function tokenOf(array $permissions, array $abilities): string
    {
        $this->app['auth']->forgetGuards();

        $user = AdminUser::query()->create([
            'name' => 'Editor',
            'email' => uniqid('editor').'@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);
        $user->roles()->attach(AdminRole::query()->create(['name' => uniqid('Role'), 'permissions' => $permissions]));

        return $user->createToken('assistant', $abilities)->plainTextToken;
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private function mcp(string $token, string $method, array $params = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params])
            ->assertOk();
    }
}
