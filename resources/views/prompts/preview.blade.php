Write a short preview text ("{!! $field !!}") for a list of records: one or two sentences that make the reader open the page.
At most {!! $limit !!} characters, plain text.

Source:
@foreach ($sources as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach
