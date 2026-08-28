<?php

namespace Tests\Unit;

use App\Repositories\Setting\SettingRepository;
use App\Services\Setting\SettingService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SettingServiceCacheTest extends TestCase
{
    public function test_it_caches_formatted_settings_and_refreshes_them_after_invalidation(): void
    {
        config()->set('cache.default', 'array');
        config()->set('performance.settings_cache_ttl', 600);
        Cache::flush();

        $repository = Mockery::mock(SettingRepository::class);
        $repository->shouldReceive('getFormattedSettings')
            ->twice()
            ->with('app')
            ->andReturn(
                ['company_name' => 'SalaTime'],
                ['company_name' => 'SalaTime updated']
            );

        $this->app->instance(SettingRepository::class, $repository);

        $service = new SettingService;

        $this->assertSame(
            ['company_name' => 'SalaTime'],
            $service->getCachedFormattedSettings('app')
        );
        $this->assertSame(
            ['company_name' => 'SalaTime'],
            $service->getCachedFormattedSettings('app')
        );

        $service->invalidateReadCaches();

        $this->assertSame(
            ['company_name' => 'SalaTime updated'],
            $service->getCachedFormattedSettings('app')
        );
    }
}
