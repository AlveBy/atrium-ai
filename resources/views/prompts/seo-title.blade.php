Write the SEO title (the <title> of the page) for this record: specific, with the main keyword near the start, no site name, no quotes.
At most {!! $limit !!} characters.

Page content:
@foreach ($sources as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
