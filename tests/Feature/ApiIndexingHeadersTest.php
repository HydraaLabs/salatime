<?php

namespace Tests\Feature;

use App\Http\Controllers\Quran\API\PrayerTime\PrayerTimeController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiIndexingHeadersTest extends TestCase
{
    private int $cachedCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::flush();

        Route::get('/api', fn () => response()->json(['status' => 'ok']));
        Route::middleware(['api', 'public.api.cache:seo-header-test,60'])
            ->get('/api/_seo-cached-json', function () {
                $this->cachedCalls++;

                return response()->json(['title' => 'Public API content', 'items' => [1, 2]]);
            });
        Route::get('/api/_seo-unavailable', fn () => abort(503, 'Temporarily unavailable.'));
        Route::get('/_seo-public-html', fn () => response('<h1>Public page</h1>')
            ->header('X-Robots-Tag', 'index, follow'));
        Route::get('/apiculture', fn () => response()->json(['subject' => 'bees']));
    }

    public function test_api_json_content_and_cache_semantics_are_preserved(): void
    {
        $first = $this->getJson('/api/_seo-cached-json');
        $first->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('X-SalaTime-Cache', 'MISS')
            ->assertExactJson(['title' => 'Public API content', 'items' => [1, 2]]);

        $second = $this->getJson('/api/_seo-cached-json');
        $second->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-SalaTime-Cache', 'HIT');
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame($first->headers->get('Cache-Control'), $second->headers->get('Cache-Control'));
        $this->assertSame($first->headers->get('ETag'), $second->headers->get('ETag'));

        $this->withHeaders(['If-None-Match' => $first->headers->get('ETag')])
            ->getJson('/api/_seo-cached-json')
            ->assertStatus(304)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-SalaTime-Cache', 'HIT')
            ->assertContent('');

        $this->assertSame(1, $this->cachedCalls);
    }

    public function test_api_root_is_also_not_indexable(): void
    {
        $this->getJson('/api')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_unknown_api_path_keeps_its_404_status_and_gets_the_header(): void
    {
        $this->get('/api/_seo-does-not-exist')
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_wrong_method_on_registered_post_route_keeps_405_without_executing_the_controller(): void
    {
        $this->mock(PrayerTimeController::class)
            ->shouldNotReceive('getPrayerCalendar');

        $this->getJson('/api/prayer-time-calendar')
            ->assertStatus(405)
            ->assertHeader('Allow', 'POST')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_authentication_error_keeps_its_json_status_and_message(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.'])
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_rendered_api_server_error_gets_the_header_without_hiding_the_error(): void
    {
        $this->getJson('/api/_seo-unavailable')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Temporarily unavailable.')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_html_robots_directive_and_body_are_unchanged(): void
    {
        $this->get('/_seo-public-html')->assertOk()
            ->assertHeader('X-Robots-Tag', 'index, follow')
            ->assertContent('<h1>Public page</h1>');
    }

    public function test_paths_starting_with_api_without_a_segment_boundary_are_unchanged(): void
    {
        $this->getJson('/apiculture')->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertExactJson(['subject' => 'bees']);
    }
}
