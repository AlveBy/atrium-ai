{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
  {{ $task->value }}|{{ $field }}|{{ $language }}|{{ $limit ?? 'none' }}|{{ $html ? 'html' : 'text' }}|{{ $text }}|{{ implode(';', array_keys($sources)) }}|{{ $target ?? '-' }}|{{ $target_code ?? '-' }}  
