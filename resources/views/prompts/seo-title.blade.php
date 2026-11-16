{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
Write the SEO title (the <title> of the page) for this record: specific, with the main keyword near the start, no site name, no quotes.
At most {!! $limit !!} characters.

Page content:
@foreach ($sources as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
