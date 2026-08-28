<?php

namespace Tests\Feature;

use App\Support\ApplicationCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicApiResponseCacheTest extends TestCase
{
    private int $successfulCalls = 0;

    private int $errorCalls = 0;

    private int $authorizedCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::flush();

        Route::middleware('public.api.cache:test-content,60')
            ->get('/_test/public-cache/{chapter}', function (Request $request, string $chapter) {
                return response()->json([
                    'call' => ++$this->successfulCalls,
                    'chapter' => $chapter,
                    'query' => $request->query(),
                ]);
            });

        Route::middleware('public.api.cache:test-errors,60')
            ->get('/_test/public-cache-error', function () {
                $status = ++$this->errorCalls === 1 ? 500 : 200;

                return response()->json(['call' => $this->errorCalls], $status);
            });

        Route::middleware('public.api.cache:test-auth,60')
            ->get('/_test/public-cache-auth', function () {
                return response()->json(['call' => ++$this->authorizedCalls]);
            });
    }

    public function test_it_caches_successful_public_json_by_route_parameters_and_canonical_query(): void
    {
        $first = $this->getJson('/_test/public-cache/1?translator_id=2&format=full');
        $second = $this->getJson('/_test/public-cache/1?format=full&translator_id=2');
        $differentParameter = $this->getJson('/_test/public-cache/2?format=full&translator_id=2');

        $first->assertOk()
            ->assertHeader('X-SalaTime-Cache', 'MISS')
            ->assertJsonPath('call', 1);
        $second->assertOk()
            ->assertHeader('X-SalaTime-Cache', 'HIT')
            ->assertJsonPath('call', 1);
        $differentParameter->assertOk()
            ->assertHeader('X-SalaTime-Cache', 'MISS')
            ->assertJsonPath('call', 2);

        $this->assertSame(2, $this->successfulCalls);
    }

    public function test_namespace_invalidation_makes_cached_responses_unreachable(): void
    {
        $this->getJson('/_test/public-cache/1')->assertJsonPath('call', 1);
        $this->getJson('/_test/public-cache/1')->assertJsonPath('call', 1);

        ApplicationCache::invalidatePublicResponses('test-content');

        $this->getJson('/_test/public-cache/1')
            ->assertHeader('X-SalaTime-Cache', 'MISS')
            ->assertJsonPath('call', 2);
    }

    public function test_conditional_requests_return_304_without_running_the_controller_again(): void
    {
        $first = $this->getJson('/_test/public-cache/1');
        $etag = $first->headers->get('ETag');

        $this->assertNotNull($etag);

        $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/_test/public-cache/1')
            ->assertStatus(304)
            ->assertHeader('X-SalaTime-Cache', 'HIT');

        $this->assertSame(1, $this->successfulCalls);
    }

    public function test_it_does_not_cache_error_responses(): void
    {
        $this->getJson('/_test/public-cache-error')->assertStatus(500);

        $this->getJson('/_test/public-cache-error')
            ->assertOk()
            ->assertHeader('X-SalaTime-Cache', 'MISS')
            ->assertJsonPath('call', 2);
    }

    public function test_it_bypasses_requests_with_authorization_headers(): void
    {
        $headers = ['Authorization' => 'Bearer test-token'];

        $this->getJson('/_test/public-cache-auth', $headers)->assertJsonPath('call', 1);
        $this->getJson('/_test/public-cache-auth', $headers)->assertJsonPath('call', 2);

        $this->assertSame(2, $this->authorizedCalls);
    }
}
