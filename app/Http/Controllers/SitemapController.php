<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\Prayer\PrayerPageCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function __invoke(PrayerPageCatalog $catalog): Response
    {
        $posts = collect();

        try {
            if (Schema::hasTable('blog_posts')) {
                $posts = BlogPost::published()
                    ->select(['slug', 'updated_at', 'published_at'])
                    ->orderByDesc('published_at')
                    ->get();
            }
        } catch (\Throwable) {
            // The core public pages must remain discoverable during DB maintenance.
        }

        $blogSignature = $posts->map(
            fn ($post) => $post->slug.':'.optional($post->updated_at ?? $post->published_at)->timestamp
        )->implode('|');
        $cacheKey = 'sitemap:v2:'.hash('sha256', implode('|', [
            config('seo.site_url'),
            CarbonImmutable::now('UTC')->format('Y-m-d-H'),
            $blogSignature,
        ]));

        $xml = Cache::remember($cacheKey, now()->addHour(), function () use ($catalog, $posts) {
            $countries = $catalog->countries();
            $dateByTimezone = [];
            $prayerPages = [];

            foreach (config('prayer_pages.locales') as $locale => $settings) {
                $prayerPages[] = [
                    'loc' => $catalog->url($locale),
                    'changefreq' => 'monthly',
                    'priority' => '0.8',
                    'lastmod' => null,
                ];
                foreach ($countries as $code => $country) {
                    $prayerPages[] = [
                        'loc' => $catalog->url($locale, $code),
                        'changefreq' => 'monthly',
                        'priority' => '0.8',
                        'lastmod' => null,
                    ];
                    foreach ($country['cities'] as $slug => $city) {
                        $tz = $city['timezone'];
                        $dateByTimezone[$tz] ??= CarbonImmutable::now($tz)->startOfDay()->toAtomString();
                        $prayerPages[] = [
                            'loc' => $catalog->url($locale, $code, $slug),
                            'changefreq' => 'daily',
                            'priority' => '0.8',
                            'lastmod' => $dateByTimezone[$tz],
                        ];
                    }
                }
            }

            return view('sitemap', compact('posts', 'prayerPages'))->render();
        });

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600, s-maxage=3600')
            ->header('Vary', 'Accept-Encoding');
    }
}
