<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai\Tests;

use Atrium\Ai\Output;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string,array{0:string,1:string,2:string,3?:?int}>
     */
    public static function answers(): array
    {
        return [
            'outer whitespace goes' => ["  \n Lake \n", 'text', 'Lake'],
            'a fence after whitespace is still a fence' => ["\n```html\n<p>A</p>\n```\n", 'html', '<p>A</p>'],
            'the language of a fence may be capitalised' => ["```HTML\nA\n```", 'html', 'A'],
            'a fenced answer may take several lines' => ["```\nline 1\nline 2\n```", 'html', "line 1\nline 2"],
            'a fence has to open the answer' => ["Here it is:\n```\nA\n```", 'html', "Here it is:\n```\nA\n```"],
            'a fence has to close the answer' => ["```\nA\n``` and that is all", 'html', "```\nA\n``` and that is all"],
            'the fenced text is trimmed' => ["```\n  A  \n```", 'html', 'A'],
            'straight quotes go' => ['"Lake Hotel"', 'text', 'Lake Hotel'],
            'single quotes go' => ["'Lake Hotel'", 'text', 'Lake Hotel'],
            'guillemets go' => ['«Hi»', 'text', 'Hi'],
            'curly quotes go' => ['“Hi”', 'text', 'Hi'],
            'quoted text is trimmed' => ['" Hi "', 'html', 'Hi'],
            'quoted text may take several lines' => ["\"line 1\nline 2\"", 'html', "line 1\nline 2"],
            'quotes in the middle stay' => ['He said "hi"', 'text', 'He said "hi"'],
            'a quote after the end stays' => ['"hi" there', 'text', '"hi" there'],
            'two quoted parts are not one quote' => ['"a" and "b"', 'text', '"a" and "b"'],
            'markup goes from a text' => ['<p> Hi </p>', 'text', 'Hi'],
            'markup stays in html' => ['<p> Hi </p>', 'html', '<p> Hi </p>'],
            'every kind of quote is decoded' => ['&quot;x&quot; &#039;y&#039; &apos;z&apos;', 'text', '"x" \'y\' \'z\''],
            'a text of the limit stays' => ['abcde', 'text', 'abcde', 5],
            'a text over the limit is cut' => ['abcdef', 'text', 'abcd…', 5],
            'a cut drops the punctuation it leaves' => ['abc, defg', 'text', 'abc…', 6],
            'a limit of zero is no limit' => ['abc', 'text', 'abc', 0],
            'a negative limit is no limit' => ['abc', 'text', 'abc', -5],
            'the limit counts letters, not bytes' => ['ééé', 'text', 'ééé', 5],
        ];
    }

    #[DataProvider('answers')]
    public function test_the_answer_is_tidied(string $answer, string $format, string $expected, ?int $max = null): void
    {
        $this->assertSame($expected, Output::clean($answer, $format, $max));
    }
}
