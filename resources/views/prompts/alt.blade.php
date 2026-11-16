{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
Write the alt text of the attached image for people who cannot see it: what is in the picture, specifically, in one sentence. Do not start with "Image of" or "Picture of".
At most {!! $limit !!} characters.
@if ($sources !== [])

The image belongs to:
@foreach ($sources as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
@endif
