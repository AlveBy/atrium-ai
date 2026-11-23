{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
Improve the text of the field "{!! $field !!}" that the editor has already written.
Correct the spelling, punctuation and grammar and make the wording clearer and smoother.
Keep the meaning, the facts, the names, the numbers, the tone and the language; do not add anything new and do not leave anything out.
@if ($fragment)
It is a piece of a longer text: answer with the improved piece only, ready to be put back in the same place.
@endif
@if ($html)
Keep the HTML as it is: every tag, attribute, link and image stays where it is; change only the words between the tags.
@else
Plain text; keep the paragraphs.
@endif
@if ($limit)
At most {!! $limit !!} characters.
@endif

Text:
{!! $text !!}
