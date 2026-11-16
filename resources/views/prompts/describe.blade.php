Write the text of the field "{!! $field !!}" for this record.
@if ($html)
Format it as simple HTML: <p> paragraphs, and <ul><li> or <strong> only where they help. No headings, no <html> or <body>.
@else
Plain text, one to three short paragraphs.
@endif
@if ($limit)
At most {!! $limit !!} characters.
@endif

What is known about the record:
@foreach ($sources as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
