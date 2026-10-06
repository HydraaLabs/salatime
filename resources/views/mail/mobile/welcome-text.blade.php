{!! $copy['title'] !!}

{!! str_replace(':name', $name, $copy['greeting']) !!}

{!! $copy['intro'] !!}

{!! $copy['preferences_title'] !!}
{!! $copy['preferences_body'] !!}

{!! $copy['cta'] !!} : {{ $siteUrl }}

{!! $copy['farewell'] !!}
{!! $copy['team'] !!}

{!! $copy['reason'] !!}
{!! $copy['privacy'] !!} : {{ rtrim($siteUrl, '/') }}/privacy-policy
