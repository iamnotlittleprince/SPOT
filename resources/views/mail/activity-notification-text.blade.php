{{ $copy['title'] }}

{{ $copy['intro'] }}
@foreach ($items as $item)

{!! $item['title'] !!}
{!! $item['body'] !!}
{!! $item['actor_name'] !!}
@endforeach

{{ $copy['button'] }}: {!! $actionUrl !!}

{{ $copy['preferences'] }}

{{ $copy['footer'] }}
