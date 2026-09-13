<section id="reading-progress-data" aria-label="Athkar and Quran reading progress">
    @foreach (['en' => 'English', 'fr' => 'Français', 'ar' => 'العربية', 'es' => 'Español'] as $language => $languageName)
        @php($readingPrivacy = trans('reading_privacy', [], $language))
        <details id="reading-progress-{{ $language }}" lang="{{ $language }}" dir="{{ $language === 'ar' ? 'rtl' : 'ltr' }}" @if ($language === 'en') open @endif>
            <summary><strong>{{ $languageName }} — {{ $readingPrivacy['title'] }}</strong></summary>
            <p><small>{{ $readingPrivacy['updated'] }}</small></p>
            @foreach (['records', 'purpose', 'guest', 'retention', 'deletion'] as $paragraph)
                <p>{{ $readingPrivacy[$paragraph] }}</p>
            @endforeach
        </details>
    @endforeach
</section>
