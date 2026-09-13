<style>
    .language-picker { position: relative; padding: 0; border: 0; border-radius: 12px; background: transparent; color: white; font-family: "Segoe UI", Tahoma, Arial, sans-serif; }
    .language-picker > summary { display: flex; align-items: center; gap: 9px; min-height: 44px; padding: 9px 13px; list-style: none; border: 1px solid rgba(255,255,255,.2); border-radius: 12px; background: rgba(255,255,255,.08); font-size: .9rem; font-weight: 700; line-height: 1.5; white-space: nowrap; cursor: pointer; }
    .language-picker > summary::-webkit-details-marker { display: none; }
    .language-picker > summary:hover, .language-picker[open] > summary { background: rgba(255,255,255,.16); }
    .language-picker .language-flag { font-size: 1.3rem; line-height: 1; }
    .language-picker .language-chevron { width: 14px; height: 14px; transition: transform .15s; }
    .language-picker[open] .language-chevron { transform: rotate(180deg); }
    .language-picker .language-links { position: absolute; z-index: 60; inset-inline-end: 0; top: calc(100% + 9px); display: grid; gap: 4px; width: 200px; max-width: calc(100vw - 32px); max-height: min(360px, 65vh); overflow-y: auto; padding: 7px; border: 1px solid rgba(255,255,255,.2); border-radius: 14px; background: {{ config('brand.primary') }}; color: white; box-shadow: 0 20px 55px rgba(0,0,0,.2); }
    .language-picker:not([open]) .language-links { display: none; }
    .language-picker .language-links a { display: flex; align-items: center; gap: 11px; min-height: 44px; padding: 10px 12px; font-size: .9rem; font-weight: 700; line-height: 1.5; border-radius: 9px; color: white; text-decoration: none; text-align: start; }
    .language-picker .language-links a[aria-current="page"], .language-picker .language-links a:hover { background: rgba(255,255,255,.14); }
    .language-picker .language-check { margin-inline-start: auto; }
    .language-picker [hidden] { display: none !important; }
    .language-picker :focus-visible { outline: 3px solid #d6a83b; outline-offset: 3px; }
    #mobile-menu .language-picker { width: max-content; }
    #mobile-menu .language-links { position: static; margin-block-start: 9px; }
    @media (max-width: 640px) { .language-picker > summary { padding: 9px 10px; gap: 7px; } }
</style>
