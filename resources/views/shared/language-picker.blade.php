@php
    $selectedLanguage = config("prayer_pages.locales.$locale");
@endphp
<details class="language-picker" data-client-side="{{ $clientSide ? 'true' : 'false' }}">
    <summary aria-label="{{ __('prayer_pages.languages', [], $locale) }}: {{ $selectedLanguage['name'] }}">
        <span class="language-flag" data-language-flag aria-hidden="true">{{ $selectedLanguage['flag'] }}</span>
        <span data-language-name lang="{{ $locale }}">{{ $selectedLanguage['name'] }}</span>
        <svg class="language-chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </summary>
    <nav class="language-links" aria-label="{{ __('prayer_pages.languages', [], $locale) }}">
        @foreach(config('prayer_pages.locales') as $languageCode => $languageSettings)
        <a href="{{ $clientSide ? url('/').'?lang='.$languageCode : $alternates[$languageSettings['language_tag']] }}" data-lang="{{ $languageCode }}" hreflang="{{ $languageSettings['language_tag'] }}" lang="{{ $languageCode }}" @if($languageCode === $locale) aria-current="page" @endif>
            <span class="language-flag" aria-hidden="true">{{ $languageSettings['flag'] }}</span>
            <span>{{ $languageSettings['name'] }}</span>
            <span class="language-check" aria-hidden="true" @if($languageCode !== $locale) hidden @endif>✓</span>
        </a>
        @endforeach
    </nav>
</details>
