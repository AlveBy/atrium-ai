<?php

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Ai\Agents\Writer;
use Atrium\Ai\Tests\Fixtures\Article;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * The same generation for external programs: the API and the MCP server,
 * with the API's tokens and the API's rules.
 */
final class ApiTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_the_api_writes_from_the_stored_record_and_saves_nothing(): void
    {
        $article = Article::query()->create(['name' => ['en' => 'Lake Hotel'], 'detail_text' => '<p>Rooms by the water, a sauna and a pier.</p>']);
        Writer::fake(['Rooms by the water with a sauna.']);

        $this->withToken($this->token(['articles.view', 'articles.update'], ['articles:read', 'articles:write']))
            ->postJson('/api/v1/ai/generate', ['module' => 'articles', 'field' => 'preview_text', 'id' => $article->id])
            ->assertOk()
            ->assertJsonPath('data.value', 'Rooms by the water with a sauna.')
            ->assertJsonPath('data.provider', 'gemini');

        Writer::assertPrompted(fn (AgentPrompt $prompt): bool => str_contains($prompt->prompt, 'a sauna and a pier'));
        $this->assertNull($article->fresh()->getTranslation('preview_text', 'en', false) ?: null);
    }

    public function test_the_api_takes_the_write_ability_and_the_owners_right(): void
    {
        Writer::fake(['Text']);
        $body = ['module' => 'articles', 'field' => 'detail_text', 'values' => ['name' => 'Lake Hotel']];

        $this->postJson('/api/v1/ai/generate', $body)->assertUnauthorized();

        $this->withToken($this->token(['articles.view', 'articles.update'], ['articles:read']))
            ->postJson('/api/v1/ai/generate', $body)->assertForbidden();

        $this->withToken($this->token(['articles.view'], ['articles:read', 'articles:write']))
            ->postJson('/api/v1/ai/generate', $body)->assertForbidden();
    }

    public function test_an_assistant_gets_a_generate_text_tool(): void
    {
        Writer::fake(['A cosy hotel by the lake.']);
        $token = $this->token(['articles.view', 'articles.update'], ['articles:read', 'articles:write']);

        $names = array_column($this->mcp($token, 'tools/list')->json('result.tools'), 'name');
        $this->assertContains('generate_text', $names);
        $this->assertContains('list_modules', $names);

        $result = $this->mcp($token, 'tools/call', ['name' => 'generate_text', 'arguments' => [
            'module' => 'articles',
            'field' => 'detail_text',
            'values' => ['name' => 'Lake Hotel'],
        ]])->json('result');

        $this->assertFalse($result['isError'], (string) ($result['content'][0]['text'] ?? ''));
        $this->assertSame('A cosy hotel by the lake.', json_decode($result['content'][0]['text'], true)['data']['value']);
    }

    /**
     * @param  array<int,string>  $permissions
     * @param  array<int,string>  $abilities
     */
    private function token(array $permissions, array $abilities): string
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
