<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Admin\Http\Controllers\Api\ResourceController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\Sites;
use Atrium\Ai\Help\Records;
use Atrium\Ai\Help\Tools\DescribeModule;
use Atrium\Ai\Help\Tools\GetRecord;
use Atrium\Ai\Help\Tools\ListModules;
use Atrium\Ai\Help\Tools\ListRecords;
use Atrium\Ai\Tests\Fixtures\Article;
use Atrium\Ai\Tests\Fixtures\Store;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Contracts\Tool;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The tools that read the modules: only what the admin may view, the way
 * the API reads it, and never all of it at once.
 */
final class HelpRecordsTest extends HelpTestCase
{
    public function test_the_tools_tell_the_model_what_they_do_and_what_they_take(): void
    {
        $admin = $this->admin();

        $this->assertSame(['arguments' => [], 'required' => []], $this->described(new ListModules($admin)));
        $this->assertSame(['arguments' => ['module' => 'string'], 'required' => ['module']], $this->described(new DescribeModule($admin)));
        $this->assertSame(['arguments' => ['module' => 'string', 'id' => 'integer'], 'required' => ['module', 'id']], $this->described(new GetRecord($admin)));
        $this->assertSame([
            'arguments' => ['module' => 'string', 'search' => 'string', 'filter' => 'object', 'sort' => 'string', 'direction' => 'string:asc|desc', 'per_page' => 'integer'],
            'required' => ['module'],
        ], $this->described(new ListRecords($admin)));
    }

    public function test_the_modules_are_those_the_admin_may_view(): void
    {
        $viewer = $this->editor(['stores.view', 'articles.edit']);

        $this->assertSame('[{"module":"stores","label":"Stores","admin_url":"/admin/stores"}]', $this->use(new ListModules($viewer)));
        $this->assertSame('[]', $this->use(new ListModules($this->stranger())));
    }

    public function test_the_names_of_the_modules_are_written_as_they_are(): void
    {
        config(['atrium.menu' => [['label' => 'Магазины', 'route' => 'admin.stores.index']]]);

        $this->assertSame('[{"module":"stores","label":"Магазины","admin_url":"/admin/stores"}]', $this->use(new ListModules($this->editor(['stores.view']))));
    }

    /**
     * @return array<string,array{0:\Closure(AdminUser):Tool,1:array<string,mixed>}>
     */
    public static function readers(): array
    {
        return [
            'describe_module' => [static fn (AdminUser $user) => new DescribeModule($user), []],
            'list_records' => [static fn (AdminUser $user) => new ListRecords($user), []],
            'get_record' => [static fn (AdminUser $user) => new GetRecord($user), ['id' => 1]],
        ];
    }

    /**
     * @param  \Closure(AdminUser):Tool  $tool
     * @param  array<string,mixed>  $arguments
     */
    #[DataProvider('readers')]
    public function test_a_module_the_admin_may_not_view_is_refused(\Closure $tool, array $arguments): void
    {
        Article::query()->create(['name' => ['en' => 'Lake Hotel']]);
        $refusal = 'No module "articles" you may view. See list_modules.';

        $this->assertSame($refusal, $this->use($tool($this->stranger()), ['module' => 'articles', ...$arguments]));
        $this->assertSame($refusal, $this->use($tool($this->editor(['articles.edit', 'stores.view'])), ['module' => 'articles', ...$arguments]));
    }

    /**
     * @param  \Closure(AdminUser):Tool  $tool
     * @param  array<string,mixed>  $arguments
     */
    #[DataProvider('readers')]
    public function test_a_module_that_is_not_installed_is_refused_even_to_a_superadmin(\Closure $tool, array $arguments): void
    {
        $this->assertSame('No module "users" you may view. See list_modules.', $this->use($tool($this->admin()), ['module' => 'users', ...$arguments]));
        $this->assertSame('No module "" you may view. See list_modules.', $this->use($tool($this->admin()), $arguments));
    }

    public function test_a_module_is_described_by_the_fields_of_its_form(): void
    {
        $fields = json_decode($this->use(new DescribeModule($this->editor(['articles.view'])), ['module' => 'articles']), true);

        $this->assertSame('name', $fields['fields'][0]['name']);
        $this->assertContains('detail_text', array_column($fields['fields'], 'name'));
        $this->assertStringNotContainsString('\\/', $this->use(new DescribeModule($this->admin()), ['module' => 'articles']));
        // Its own fields, not those of the first module.
        $this->assertSame(['name'], array_column(json_decode($this->use(new DescribeModule($this->admin()), ['module' => 'stores']), true)['fields'], 'name'));
    }

    public function test_the_records_come_with_their_count_and_their_admin_address(): void
    {
        $article = Article::query()->create(['name' => ['en' => 'Lake Hotel'], 'detail_text' => 'Rooms by the water.']);

        $list = json_decode($this->use(new ListRecords($this->editor(['articles.view'])), ['module' => 'articles']), true);

        $this->assertSame(1, $list['meta']['total']);
        $this->assertSame('Lake Hotel', $list['data'][0]['name']['en'] ?? $list['data'][0]['name']);
        $this->assertStringEndsWith("/admin/articles/{$article->id}/edit", $list['data'][0]['admin_url']);
    }

    public function test_the_records_are_given_as_readable_text(): void
    {
        $store = Store::query()->create(['name' => 'Невский/2']);

        $this->assertStringContainsString('"name":"Невский/2"', $this->use(new ListRecords($this->admin()), ['module' => 'stores']));
        $this->assertStringContainsString('"name":"Невский/2"', $this->use(new GetRecord($this->admin()), ['module' => 'stores', 'id' => $store->id]));
    }

    public function test_the_records_of_another_site_are_not_listed_or_read(): void
    {
        $mine = Store::query()->create(['name' => 'Main street', 'site_id' => $this->mainSite()->id]);
        $theirs = Store::query()->create(['name' => 'Harbour', 'site_id' => $this->secondSite()->id]);
        app(Sites::class)->setCurrent($this->mainSite());
        $viewer = $this->editor(['stores.view']);

        $list = json_decode($this->use(new ListRecords($viewer), ['module' => 'stores']), true);

        $this->assertSame(['Main street'], array_column($list['data'], 'name'));
        $this->assertStringContainsString('Main street', $this->use(new GetRecord($viewer), ['module' => 'stores', 'id' => $mine->id]));
        $this->assertSame('No such record.', $this->use(new GetRecord($viewer), ['module' => 'stores', 'id' => $theirs->id]));
    }

    public function test_records_are_found_by_text_in_any_script(): void
    {
        foreach (['Main street', 'Невский проспект', '銀座店', 'شارع الملك'] as $name) {
            Store::query()->create(['name' => $name]);
        }

        foreach (['street' => 'Main street', 'Невский' => 'Невский проспект', '銀座' => '銀座店', 'الملك' => 'شارع الملك'] as $search => $name) {
            $this->assertSame([$name], $this->names(['search' => $search]), $search);
        }

        $this->assertSame([], $this->names(['search' => 'zebra']));
    }

    public function test_records_are_filtered_by_exact_values(): void
    {
        Store::query()->create(['name' => 'Main street']);
        Store::query()->create(['name' => 'Main street 2']);

        $this->assertSame(['Main street'], $this->names(['filter' => ['name' => 'Main street']]));
    }

    public function test_records_are_sorted_by_the_column_and_the_direction_asked(): void
    {
        foreach (['Bravo', 'Charlie', 'Alpha'] as $name) {
            Store::query()->create(['name' => $name]);
        }

        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $this->names([]));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->names(['sort' => 'name']));
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->names(['sort' => 'name', 'direction' => 'desc']));
        $this->assertSame(['Alpha', 'Charlie', 'Bravo'], $this->names(['direction' => 'desc']));
    }

    /**
     * @return array<string,array{0:mixed,1:int}>
     */
    public static function pageSizes(): array
    {
        return [
            'none' => [null, 5],
            'nought' => [0, 5],
            'empty' => ['', 5],
            'one' => [1, 1],
            'three' => [3, 3],
            'ten' => [10, 10],
            'eleven' => [11, 10],
            'a hundred' => [100, 10],
            'below nought' => [-5, 1],
            'a number in a string' => ['7', 7],
        ];
    }

    #[DataProvider('pageSizes')]
    public function test_a_few_records_are_returned_at_a_time(mixed $asked, int $returned): void
    {
        foreach (range(1, 12) as $number) {
            Store::query()->create(['name' => "Store {$number}"]);
        }
        $arguments = ['module' => 'stores'];

        if ($asked !== null) {
            $arguments['per_page'] = $asked;
        }

        $list = json_decode($this->use(new ListRecords($this->admin()), $arguments), true);

        $this->assertSame($returned, $list['meta']['per_page']);
        $this->assertCount($returned, $list['data']);
        $this->assertSame(12, $list['meta']['total']);
    }

    public function test_one_record_is_read_with_all_its_fields(): void
    {
        $article = Article::query()->create(['name' => ['en' => 'Lake Hotel'], 'detail_text' => 'Rooms by the water.']);
        $viewer = $this->editor(['articles.view']);

        $record = json_decode($this->use(new GetRecord($viewer), ['module' => 'articles', 'id' => $article->id]), true);

        $this->assertSame($article->id, $record['data']['id']);
        $this->assertSame('Rooms by the water.', $record['data']['detail_text']);
        $this->assertSame('No such record.', $this->use(new GetRecord($viewer), ['module' => 'articles', 'id' => $article->id + 1]));
        $this->assertSame('No such record.', $this->use(new GetRecord($viewer), ['module' => 'articles']));
    }

    public function test_a_long_record_is_cut_at_twelve_thousand_characters_not_bytes(): void
    {
        $article = Article::query()->create(['name' => ['en' => 'Lake Hotel'], 'detail_text' => str_repeat('я', 13000)]);

        $answer = $this->use(new GetRecord($this->admin()), ['module' => 'articles', 'id' => $article->id]);

        $this->assertSame(12000, mb_strlen($answer));
        $this->assertTrue(mb_check_encoding($answer, 'UTF-8'));
    }

    public function test_a_refusal_of_the_api_is_handed_on_as_it_is(): void
    {
        $this->mock(ResourceController::class, static function (MockInterface $api): void {
            $api->shouldReceive('listItems')->andThrow(new HttpException(429, 'Too many requests.'));
        });

        $this->assertSame('HTTP 429. Too many requests.', $this->use(new ListRecords($this->admin()), ['module' => 'stores']));
    }

    public function test_a_failure_while_reading_is_reported_and_not_shown_to_the_model(): void
    {
        Exceptions::fake();
        $this->mock(ResourceController::class, static function (MockInterface $api): void {
            $api->shouldReceive('showItem')->andThrow(new RuntimeException('SQLSTATE: secret table'));
        });

        $this->assertSame('The records could not be read.', $this->use(new GetRecord($this->admin()), ['module' => 'stores', 'id' => 1]));
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'SQLSTATE: secret table');
    }

    public function test_the_permissions_alone_decide_whatever_the_token_of_the_admin(): void
    {
        Store::query()->create(['name' => 'Main street']);
        $viewer = $this->editor(['stores.view']);
        $viewer->withAccessToken(new PersonalAccessToken(['abilities' => []]));

        $this->assertSame(1, json_decode(app(Records::class)->list($viewer, 'stores', []), true)['meta']['total']);
    }

    /**
     * @param  array<string,mixed>  $arguments
     * @return list<string>
     */
    private function names(array $arguments): array
    {
        $list = json_decode($this->use(new ListRecords($this->admin()), ['module' => 'stores', ...$arguments]), true);

        return array_column($list['data'], 'name');
    }
}
