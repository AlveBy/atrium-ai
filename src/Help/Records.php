<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Help;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Http\Controllers\Api\ResourceController;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The helper reads records the way the API does, and only what the admin
 * who asked may see: the API's own controller does the reading, with the
 * admin's permissions (an unsaved token that may do anything stands in for
 * one, so the permissions alone decide). Reading only: the helper never writes.
 */
final class Records
{
    public function __construct(private readonly ApiResources $resources) {}

    /**
     * The modules the admin may view.
     *
     * @return list<array{module:string,label:string,admin_url:string}>
     */
    public function modules(AdminUser $user): array
    {
        $modules = [];

        foreach (array_keys($this->resources->all()) as $key) {
            if ($user->hasAdminPermission("{$key}.view")) {
                $modules[] = ['module' => $key, 'label' => $this->resources->label($key), 'admin_url' => route("admin.{$key}.index", [], false)];
            }
        }

        return $modules;
    }

    public function mayView(AdminUser $user, string $module): bool
    {
        return $this->resources->model($module) !== null && $user->hasAdminPermission("{$module}.view");
    }

    /**
     * @param  array<string,mixed>  $query
     */
    public function list(AdminUser $user, string $module, array $query): string
    {
        return $this->call($user, $module, $query, static fn (ResourceController $api, Request $request): JsonResponse => $api->listItems($request, $module));
    }

    public function get(AdminUser $user, string $module, int $id): string
    {
        return $this->call($user, $module, [], static fn (ResourceController $api, Request $request): JsonResponse => $api->showItem($request, $module, $id));
    }

    /**
     * @param  array<string,mixed>  $query
     * @param  callable(ResourceController, Request): JsonResponse  $read
     */
    private function call(AdminUser $user, string $module, array $query, callable $read): string
    {
        if (! $this->mayView($user, $module)) {
            return 'No module "'.$module.'" you may view. See list_modules.';
        }

        $request = Request::create('/', 'GET', $query);
        // Never saved: it only lets the permissions decide.
        $token = new PersonalAccessToken(['abilities' => ['*']]);
        $request->setUserResolver(static fn (): AdminUser => $user->withAccessToken($token));

        try {
            // Enough for an answer, not a whole table in one prompt.
            // Readable text, not \u escapes: more of it fits.
            return mb_substr(json_encode($read(app(ResourceController::class), $request)->getData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 12000);
        } catch (ModelNotFoundException) {
            return 'No such record.';
        } catch (HttpExceptionInterface $e) {
            return 'HTTP '.$e->getStatusCode().'. '.$e->getMessage();
        } catch (Throwable $e) {
            report($e);

            return 'The records could not be read.';
        }
    }
}
