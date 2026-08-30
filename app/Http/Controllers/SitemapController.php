<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function __invoke(): Response
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

        return response()
            ->view('sitemap', compact('posts'))
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
