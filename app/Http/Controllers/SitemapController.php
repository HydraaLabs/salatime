<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\Prayer\PrayerPageCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
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

        $prayerPages = collect(config('prayer_pages.locales'))->flatMap(function ($settings, $locale) use ($catalog) {
            $pages = [['loc' => $catalog->url($locale), 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => null]];
            foreach ($catalog->countries() as $code => $country) {
                $pages[] = ['loc' => $catalog->url($locale, $code), 'changefreq' => 'monthly', 'priority' => '0.8', 'lastmod' => null];
                foreach ($country['cities'] as $slug => $city) {
                    $pages[] = [
                        'loc' => $catalog->url($locale, $code, $slug), 'changefreq' => 'daily', 'priority' => '0.8',
                        'lastmod' => CarbonImmutable::now($city['timezone'])->startOfDay()->toAtomString(),
                    ];
                }
            }

            return $pages;
        });

        return response()
            ->view('sitemap', compact('posts', 'prayerPages'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
