{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
List 5 to 10 search keywords and phrases for this page, the most important first, separated by commas, lowercase.
At most {!! $limit !!} characters in total.

Page content:
@foreach ($sources as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
