<?php

namespace App\Http\Controllers\Quran\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Setting\SettingService;

class BlogPublicController extends Controller
{
    public function index()
    {
        $posts = BlogPost::published()
            ->orderByDesc('published_at')
            ->paginate(9);

        $s = $this->seoSettings();

        return response()
            ->view('blog.index', compact('posts', 's'))
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600')
            ->header('Vary', 'Accept-Encoding');
    }

    public function show(string $slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();
        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->latest('published_at')
            ->limit(3)
            ->get();

        $s = $this->seoSettings();

        return response()
            ->view('blog.show', compact('post', 'related', 's'))
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600')
            ->header('Vary', 'Accept-Encoding');
    }

    private function seoSettings(): array
    {
        try {
            $landing = resolve(SettingService::class)->getCachedFormattedSettings('landing');
            $app = resolve(SettingService::class)->getCachedFormattedSettings('app');
        } catch (\Throwable) {
            $landing = [];
            $app = [];
        }

        return array_merge([
            'app_name' => 'SalaTime',
            'web_logo' => $app['web_logo'] ?? null,
        ], (array) $landing, (array) $app);
    }
}
