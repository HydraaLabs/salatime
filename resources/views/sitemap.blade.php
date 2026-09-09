{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ config('seo.site_url') }}/</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ config('seo.site_url') }}/privacy-policy</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ config('seo.site_url') }}/terms-and-conditions</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ config('seo.site_url') }}/support</loc>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>
    @foreach($prayerPages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
        @if($page['lastmod'])<lastmod>{{ $page['lastmod'] }}</lastmod>@endif
        <changefreq>{{ $page['changefreq'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
    </url>
    @endforeach
    @if($posts->isNotEmpty())
    <url>
        <loc>{{ config('seo.site_url') }}/blog</loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    @foreach($posts as $post)
    <url>
        <loc>{{ config('seo.site_url') }}/blog/{{ rawurlencode($post->slug) }}</loc>
        <lastmod>{{ ($post->updated_at ?? $post->published_at)->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
    @endif
</urlset>
