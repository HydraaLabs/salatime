<section id="delete-account" aria-label="Delete your SalaTime account or data" tabindex="-1">
    @foreach (['en' => 'English', 'fr' => 'Français', 'ar' => 'العربية', 'es' => 'Español'] as $language => $languageName)
        @php($deletion = trans('account_deletion', [], $language))
        <details id="delete-account-{{ $language }}" lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" @if ($language === 'en') open @endif>
            <summary><strong>{{ $languageName }} — {{ $deletion['title'] }}</strong></summary>
            <p>{{ $deletion['request'] }}</p>
            <!--email_off-->
            <p><a href="mailto:contact@salatime.net?subject={{ rawurlencode($deletion['subject']) }}">{{ $deletion['link'] }} — <bdi>contact@salatime.net</bdi></a></p>
            <!--/email_off-->
            @foreach (['data_only', 'verification', 'scope'] as $paragraph)
                <p>{{ $deletion[$paragraph] }}</p>
            @endforeach
        </details>
    @endforeach
</section>
