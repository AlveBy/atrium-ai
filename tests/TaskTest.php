<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Task;
use PHPUnit\Framework\Attributes\DataProvider;

final class TaskTest extends TestCase
{
    /**
     * @return array<string,array{0:Task,1:?int,2:string}>
     */
    public static function tasks(): array
    {
        return [
            'describe' => [Task::Describe, null, 'text'],
            'preview' => [Task::Preview, 300, 'text'],
            'seo title' => [Task::SeoTitle, 60, 'text'],
            'seo description' => [Task::SeoDescription, 160, 'text'],
            'seo keywords' => [Task::SeoKeywords, 255, 'text'],
            'alt' => [Task::Alt, 125, 'vision'],
            'translate' => [Task::Translate, null, 'text'],
        ];
    }

    #[DataProvider('tasks')]
    public function test_a_task_asks_for_its_length_and_its_kind_of_provider(Task $task, ?int $limit, string $kind): void
    {
        $this->assertSame($limit, $task->limit());
        $this->assertSame($kind, $task->kind());
    }
}
