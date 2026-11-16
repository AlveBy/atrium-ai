{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
Write the meta description of this page for search results: what the visitor finds here and why to click, in one or two sentences.
At most {!! $limit !!} characters, plain text.

Page content:
@foreach ($sources as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
