<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Http\Controllers\GenerateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The two requests of the "Generate" button: start, and ask for the answer.
 */
final class GenerateEndpointTest extends TestCase
{
    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function invalid(): array
    {
        $ok = ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']];

        return [
            'no module' => [['module' => null] + $ok, 'module'],
            'no field' => [['field' => null] + $ok, 'field'],
            'a module left out' => [array_diff_key($ok, ['module' => 1]), 'module'],
            'a field left out' => [array_diff_key($ok, ['field' => 1]), 'field'],
            'values that are not a list' => [['values' => 'Lake'] + $ok, 'values'],
            'a language code too long' => [['locale' => str_repeat('x', 21)] + $ok, 'locale'],
            'a translate that is not a flag' => [['translate' => 'maybe'] + $ok, 'translate'],
            'a module name too long' => [['module' => str_repeat('x', 101)] + $ok, 'module'],
            'a field name too long' => [['field' => str_repeat('x', 101)] + $ok, 'field'],
        ];
    }

    /**
     * @param  array<string,mixed>  $body
     */
    #[DataProvider('invalid')]
    public function test_a_request_that_is_not_in_order_is_refused_before_anything_is_asked(array $body, string $field): void
    {
        Writer::fake()->preventStrayPrompts();

        $this->actingAs($this->admin(), 'admin')->postJson('/admin/ai/generate', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        Writer::assertNeverPrompted();
    }

    public function test_what_is_left_out_may_be_null(): void
    {
        Writer::fake(['Text']);

        $this->actingAs($this->admin(), 'admin')
            ->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => null, 'locale' => null, 'translate' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field')
            ->assertJsonMissingValidationErrors(['values', 'locale', 'translate']);
    }

    public function test_the_answer_comes_with_the_id_to_ask_again_with(): void
    {
        Writer::fake(['Text']);

        $response = $this->actingAs($this->admin(), 'admin')
            ->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']])
            ->assertStatus(202);

        $id = $response->json('id');
        $this->assertIsString($id);
        $this->getJson('/admin/ai/generate/'.$id)->assertOk()->assertJsonPath('id', $id)->assertJsonPath('status', 'done');
        $this->getJson('/admin/ai/generate/unknown')->assertNotFound();
    }

    public function test_a_generation_with_nowhere_to_keep_its_answer_is_pending(): void
    {
        Queue::fake();
        config(['cache.default' => 'null', 'atrium-ai.queue.connection' => 'database']);

        $this->actingAs($this->admin(), 'admin')
            ->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']])
            ->assertStatus(202)
            ->assertJsonPath('status', 'pending');
    }

    public function test_an_unknown_module_is_a_404_and_an_editor_without_the_right_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->postJson('/admin/ai/generate', ['module' => 'recipes', 'field' => 'detail_text'])
            ->assertNotFound();

        $this->actingAs($this->editor(['articles.view']), 'admin')
            ->postJson('/admin/ai/generate', ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake']])
            ->assertForbidden()
            ->assertJsonPath('message', __('atrium::permissions.forbidden'));
    }

    public function test_the_actions_are_public(): void
    {
        $this->assertTrue((new ReflectionMethod(GenerateController::class, 'store'))->isPublic());
        $this->assertTrue((new ReflectionMethod(GenerateController::class, 'show'))->isPublic());

        $request = Request::create('/admin/ai/generate/x');
        $request->setUserResolver(fn () => $this->admin());

        $this->expectException(NotFoundHttpException::class);

        app(GenerateController::class)->show($request, 'x');
    }
}
