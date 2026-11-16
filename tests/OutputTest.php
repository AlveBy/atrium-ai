<?php

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Output;
use PHPUnit\Framework\TestCase;

final class OutputTest extends TestCase
{
    public function test_it_drops_what_models_wrap_their_answers_in(): void
    {
        $this->assertSame('Lake Hotel', Output::clean("```\n\"Lake Hotel\"\n```", 'text'));
        $this->assertSame('<p>Rooms</p>', Output::clean("```html\n<p>Rooms</p>\n```", 'html'));
        $this->assertSame('Rooms & sauna', Output::clean('<p>Rooms &amp; sauna</p>', 'text'));
    }

    public function test_it_keeps_to_the_limit(): void
    {
        $this->assertSame('abcd…', Output::clean('abcdefgh', 'text', 5));
        $this->assertSame('abc', Output::clean('abc', 'text', 5));
    }
}
