{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
Translate the text below into {!! $target !!}.
Keep the meaning, the tone and the names; adapt idioms instead of translating them word for word.
@if ($html)
Keep every HTML tag and attribute exactly as it is; translate only the text between the tags.
@endif
Answer with the translation only.

{!! $text !!}
