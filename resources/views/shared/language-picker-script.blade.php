<script>
(() => {
    @php($languages = collect(config('prayer_pages.locales'))->mapWithKeys(fn ($settings, $code) => [$code => ['name' => $settings['name'], 'flag' => $settings['flag'], 'label' => __('prayer_pages.languages', [], $code)]]))
    const languages = @json($languages);
    const pickers = [...document.querySelectorAll('.language-picker')];
    window.updateLanguagePickers = lang => {
        const selected = languages[lang];
        if (!selected) return;
        pickers.forEach(picker => {
            picker.open = false;
            picker.querySelector('[data-language-flag]').textContent = selected.flag;
            const name = picker.querySelector('[data-language-name]');
            name.textContent = selected.name;
            name.lang = lang;
            picker.querySelector('summary').setAttribute('aria-label', selected.label + ': ' + selected.name);
            picker.querySelector('nav').setAttribute('aria-label', selected.label);
            picker.querySelectorAll('[data-lang]').forEach(link => {
                const active = link.dataset.lang === lang;
                if (active) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
                link.querySelector('.language-check').hidden = !active;
            });
        });
    };
    pickers.forEach(picker => {
        picker.addEventListener('toggle', () => {
            if (picker.open) pickers.forEach(other => { if (other !== picker) other.open = false; });
        });
        picker.querySelectorAll('[data-lang]').forEach(link => link.addEventListener('click', event => {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            try { localStorage.setItem('zabi_lang', link.dataset.lang); } catch (_) {}
            if (picker.dataset.clientSide === 'true' && typeof window.setLanguage === 'function') {
                event.preventDefault();
                window.setLanguage(link.dataset.lang);
                const url = new URL(location.href);
                url.searchParams.set('lang', link.dataset.lang);
                history.replaceState(null, '', url);
                picker.querySelector('summary').focus();
            }
        }));
    });
    document.addEventListener('click', event => {
        pickers.forEach(picker => { if (!picker.contains(event.target)) picker.open = false; });
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') pickers.forEach(picker => {
            if (picker.open) { picker.open = false; picker.querySelector('summary').focus(); }
        });
    });
})();
</script>
