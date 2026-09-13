<?php

namespace Tests\Feature;

use App\Models\Mobile\MobileAccount;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileReadingProgressApiTest extends TestCase
{
    private const MIGRATION = 'migrations/2026_09_13_020000_create_mobile_reading_progress_tables.php';

    private MobileAccount $account;

    private array $headers;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.foreign_key_constraints' => true,
                'cache.default' => 'array',
                'mail.default' => 'array',
                'queue.default' => 'sync',
                'logging.default' => 'null',
                'mobile_auth.enabled' => true,
                'mobile_auth.mail_enabled' => false,
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        (require database_path('migrations/2026_09_12_190000_create_mobile_accounts_tables.php'))->up();
        (require database_path(self::MIGRATION))->up();
        Notification::fake();
        Http::preventStrayRequests();
        Cache::flush();
        $this->account = MobileAccount::create(['name' => 'Reading Test', 'email' => 'reading@example.test']);
        $this->headers = $this->tokenHeaders($this->account);
    }

    private function tokenHeaders(MobileAccount $account): array
    {
        return ['Authorization' => 'Bearer '.$account->createToken('test', ['mobile:account'])->plainTextToken];
    }

    private function operation(array $changes = []): array
    {
        return array_replace(['id' => (string) Str::uuid(), 'kind' => 'athkar', 'itemKey' => 'morning:1', 'day' => '2026-09-13', 'count' => 1], $changes);
    }

    private function batch(array $operations, ?array $headers = null)
    {
        return $this->postJson('/api/mobile/reading-progress/batch', ['operations' => $operations], $headers ?? $this->headers);
    }

    public function test_empty_snapshot_and_an_unverified_mobile_account_can_save(): void
    {
        $this->assertNull($this->account->email_verified_at);
        $this->getJson('/api/mobile/reading-progress', $this->headers)
            ->assertOk()->assertExactJson(['data' => ['entries' => [], 'cursor' => 0, 'hasMore' => false]]);
        $operation = $this->operation();
        $this->batch([$operation])->assertOk()->assertExactJson(['data' => [
            'acknowledged' => [$operation['id']],
            'entries' => [['kind' => 'athkar', 'itemKey' => 'morning:1', 'day' => '2026-09-13', 'count' => 1, 'revision' => 1]],
        ]]);
        Notification::assertNothingSent();
    }

    public function test_authentication_ability_and_feature_gate_apply_to_both_endpoints(): void
    {
        $this->getJson('/api/mobile/reading-progress')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->postJson('/api/mobile/reading-progress/batch', ['operations' => [$this->operation()]])->assertUnauthorized();
        $unrelated = ['Authorization' => 'Bearer '.$this->account->createToken('wrong', ['other:ability'])->plainTextToken];
        $this->getJson('/api/mobile/reading-progress', $unrelated)->assertUnauthorized();
        config(['mobile_auth.enabled' => false]);
        $this->getJson('/api/mobile/reading-progress', $this->headers)->assertStatus(503)->assertJsonPath('code', 'auth_unavailable');
        $this->batch([$this->operation()])->assertStatus(503);
        $this->assertDatabaseCount('mobile_reading_entries', 0);
    }

    public function test_accounts_are_isolated_even_when_operation_identifiers_match(): void
    {
        $other = MobileAccount::create(['name' => 'Other Test', 'email' => 'other-reading@example.test']);
        $otherHeaders = $this->tokenHeaders($other);
        $op = $this->operation();
        $this->batch([$op])->assertOk();
        $this->getJson('/api/mobile/reading-progress', $otherHeaders)->assertJsonCount(0, 'data.entries');
        $this->batch([array_replace($op, ['count' => 0])], $otherHeaders)
            ->assertOk()->assertJsonPath('data.entries.0.count', 0)->assertJsonPath('data.entries.0.revision', 1);
        $this->getJson('/api/mobile/reading-progress', $this->headers)->assertJsonPath('data.entries.0.count', 1);
        $this->assertDatabaseCount('mobile_reading_entries', 2);
        $this->assertDatabaseCount('mobile_reading_operations', 2);
    }

    public function test_retries_never_overwrite_a_newer_absolute_count(): void
    {
        $first = $this->operation();
        $second = $this->operation(['count' => 0]);
        $this->batch([$first])->assertOk()->assertJsonPath('data.entries.0.revision', 1);
        $this->batch([$second])->assertOk()->assertJsonPath('data.entries.0.revision', 2);
        $retry = $this->batch([$first, $first])->assertOk()
            ->assertJsonPath('data.acknowledged', [$first['id']])
            ->assertJsonPath('data.entries.0.count', 0)->assertJsonPath('data.entries.0.revision', 2);
        $this->assertArrayNotHasKey('cursor', $retry->json('data'));
        $this->assertSame(2, (int) $this->account->fresh()->reading_progress_revision);
        $this->assertDatabaseCount('mobile_reading_operations', 2);
        $this->getJson('/api/mobile/reading-progress?after=1', $this->headers)
            ->assertJsonPath('data.entries.0.count', 0)->assertJsonPath('data.cursor', 2);
    }

    public function test_a_batch_uses_input_order_and_returns_only_each_final_affected_record(): void
    {
        $first = $this->operation();
        $second = $this->operation(['count' => 0]);
        $this->batch([$first, $second, $first])->assertOk()
            ->assertJsonPath('data.acknowledged', [$first['id'], $second['id']])
            ->assertJsonCount(1, 'data.entries')->assertJsonPath('data.entries.0.count', 0)
            ->assertJsonPath('data.entries.0.revision', 2);
        $third = $this->operation();
        $this->batch([$third])->assertOk()->assertJsonPath('data.entries.0.count', 1)
            ->assertJsonPath('data.entries.0.revision', 3);
    }

    public function test_reused_uuid_with_different_content_rolls_back_the_entire_batch(): void
    {
        $first = $this->operation();
        $this->batch([$first])->assertOk();
        foreach ([['count' => 0], ['day' => '2026-09-14'], ['kind' => 'quran', 'itemKey' => '1:1']] as $changed) {
            $this->batch([$this->operation(['day' => '2026-09-15']), array_replace($first, $changed)])
                ->assertStatus(409)->assertJsonPath('code', 'reading_operation_conflict');
            $this->assertDatabaseCount('mobile_reading_entries', 1);
            $this->assertDatabaseCount('mobile_reading_operations', 1);
            $this->assertSame(1, (int) $this->account->fresh()->reading_progress_revision);
        }
    }

    public function test_uuid_case_is_deduplicated_and_acknowledgement_keeps_the_sent_spelling(): void
    {
        $op = $this->operation(['id' => 'abcd1234-1234-4123-a123-abcdef123456']);
        $this->batch([$op])->assertOk();
        $op['id'] = strtoupper($op['id']);
        $this->batch([$op])->assertOk()->assertJsonPath('data.acknowledged', [$op['id']])
            ->assertJsonPath('data.entries.0.revision', 1);
        $this->assertDatabaseCount('mobile_reading_operations', 1);
    }

    public function test_incremental_pagination_preserves_tombstones_and_does_not_skip_concurrent_changes(): void
    {
        $this->batch([
            $this->operation(['kind' => 'quran', 'itemKey' => '1:1']),
            $this->operation(['kind' => 'quran', 'itemKey' => '1:2']),
            $this->operation(['kind' => 'quran', 'itemKey' => '1:3']),
        ])->assertOk();
        $this->getJson('/api/mobile/reading-progress?after=0&limit=2', $this->headers)
            ->assertJsonPath('data.cursor', 2)->assertJsonPath('data.hasMore', true)
            ->assertJsonPath('data.entries.0.revision', 1)->assertJsonPath('data.entries.1.revision', 2);
        $this->batch([
            $this->operation(['kind' => 'quran', 'itemKey' => '1:1', 'count' => 0]),
            $this->operation(['kind' => 'quran', 'itemKey' => '1:4']),
        ])->assertOk();
        $this->getJson('/api/mobile/reading-progress?after=2&limit=2', $this->headers)
            ->assertJsonPath('data.cursor', 4)->assertJsonPath('data.hasMore', true)
            ->assertJsonPath('data.entries.0.itemKey', '1:3')
            ->assertJsonPath('data.entries.1.itemKey', '1:1')->assertJsonPath('data.entries.1.count', 0);
        $this->getJson('/api/mobile/reading-progress?after=4&limit=2', $this->headers)
            ->assertJsonPath('data.cursor', 5)->assertJsonPath('data.hasMore', false)->assertJsonCount(1, 'data.entries');
        $this->getJson('/api/mobile/reading-progress?after=5', $this->headers)
            ->assertExactJson(['data' => ['entries' => [], 'cursor' => 5, 'hasMore' => false]]);
        $this->assertDatabaseCount('mobile_reading_entries', 4);
    }

    public function test_all_catalogue_targets_and_quran_chapter_boundaries_are_accepted(): void
    {
        $athkar = config('mobile_reading_items.athkar');
        $quran = config('mobile_reading_items.quran');
        $this->assertCount(218, $athkar);
        $this->assertCount(114, $quran);
        $this->assertSame(6236, array_sum($quran));
        $this->assertSame(19, $quran[87]);
        $ops = [];
        foreach ($athkar as $key => $count) {
            $ops[] = $this->operation(['itemKey' => $key, 'count' => $count]);
        }
        foreach ($quran as $chapter => $count) {
            $ops[] = $this->operation(['kind' => 'quran', 'itemKey' => $chapter.':'.$count]);
        }
        foreach (array_chunk($ops, 100) as $batch) {
            $this->batch($batch)->assertOk()->assertJsonCount(count($batch), 'data.acknowledged');
        }
        $this->assertDatabaseCount('mobile_reading_entries', 332);
        $this->assertSame(332, (int) $this->account->fresh()->reading_progress_revision);
    }

    public function test_invalid_values_are_rejected_without_partial_writes(): void
    {
        $cases = [
            ['id' => 'not-a-uuid'], ['id' => 12], ['id' => ' '.Str::uuid()], ['id' => null],
            ['kind' => 'Quran'], ['kind' => 1], ['itemKey' => 'unknown:1'], ['itemKey' => ' morning:1 '], ['itemKey' => 1],
            ['count' => '1'], ['count' => 1.0], ['count' => true], ['count' => null], ['count' => -1], ['count' => 2],
            ['day' => '1999-12-31'], ['day' => '2101-01-01'], ['day' => '2100-02-29'], ['day' => '2026-02-30'],
            ['day' => '2026-9-13'], ['day' => '2026-09-13 '], ['day' => 20260913], ['day' => null],
            ['kind' => 'quran', 'itemKey' => '0:1'], ['kind' => 'quran', 'itemKey' => '1:0'],
            ['kind' => 'quran', 'itemKey' => '01:1'], ['kind' => 'quran', 'itemKey' => '1:01'],
            ['kind' => 'quran', 'itemKey' => '1:8'], ['kind' => 'quran', 'itemKey' => '87:20'],
            ['kind' => 'quran', 'itemKey' => '115:1'], ['kind' => 'quran', 'itemKey' => '1:1', 'count' => 2],
        ];
        foreach ($cases as $changes) {
            Cache::flush();
            // Preserve 1.0 in JSON so a floating point count cannot become an integer.
            $body = json_encode(['operations' => [$this->operation(), $this->operation($changes)]], JSON_PRESERVE_ZERO_FRACTION);
            $this->call('POST', '/api/mobile/reading-progress/batch', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => $this->headers['Authorization'],
            ], $body)->assertUnprocessable();
            $this->assertDatabaseCount('mobile_reading_entries', 0);
            $this->assertDatabaseCount('mobile_reading_operations', 0);
        }
        $this->assertSame(0, (int) $this->account->fresh()->reading_progress_revision);
    }

    public function test_unknown_fields_non_json_objects_and_oversized_batches_are_rejected(): void
    {
        $op = $this->operation();
        foreach ([[], ['operations' => []], ['operations' => array_fill(0, 101, $op)],
            ['operations' => [$op], 'accountId' => 2], ['operations' => [$op + ['accountId' => 2]]],
            ['operations' => [$op + ['revision' => 999]]], ['operations' => [$op + ['timestamp' => 123]]],
            ['operations' => [array_diff_key($op, ['count' => true])]],
            ['operations' => ['0' => $op, '2' => $op]], ['operations' => [null]],
        ] as $payload) {
            $this->postJson('/api/mobile/reading-progress/batch', $payload, $this->headers)->assertUnprocessable();
        }
        $this->call('POST', '/api/mobile/reading-progress/batch', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $this->headers['Authorization'],
        ], '{invalid')->assertUnprocessable();
        $this->withHeaders($this->headers)->post('/api/mobile/reading-progress/batch', ['operations' => [$op]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('mobile_reading_entries', 0);
    }

    public function test_date_boundaries_and_leap_days_are_retained_as_calendar_days(): void
    {
        $ops = array_map(fn ($day) => $this->operation(['day' => $day]), ['2000-01-01', '2000-02-29', '2024-02-29', '2100-12-31']);
        $this->batch($ops)->assertOk()->assertJsonCount(4, 'data.entries');
        $this->getJson('/api/mobile/reading-progress', $this->headers)
            ->assertJsonPath('data.entries.1.day', '2000-02-29')->assertJsonPath('data.entries.3.day', '2100-12-31');
    }

    public function test_pagination_rejects_invalid_limits_cursors_and_account_injection(): void
    {
        foreach (['limit=0', 'limit=501', 'limit=1.0', 'limit[]=1', 'after=-1', 'after=1.0', 'after=01',
            'after=9007199254740992', 'after[]=0', 'accountId=2', 'limit=abc'] as $query) {
            $this->getJson('/api/mobile/reading-progress?'.$query, $this->headers)->assertUnprocessable();
        }
    }

    public function test_reading_routes_share_the_mobile_preferences_throttle(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/mobile/reading-progress', $this->headers)->assertOk();
        }
        $this->batch([$this->operation()])->assertStatus(429);
        $this->assertDatabaseCount('mobile_reading_entries', 0);
    }

    public function test_account_deletion_cascades_both_progress_tables_without_touching_another_account(): void
    {
        $other = MobileAccount::create(['name' => 'Other Test', 'email' => 'cascade@example.test']);
        $this->batch([$this->operation()])->assertOk();
        $this->batch([$this->operation()], $this->tokenHeaders($other))->assertOk();
        $this->account->delete();
        $this->assertDatabaseCount('mobile_reading_entries', 1);
        $this->assertDatabaseCount('mobile_reading_operations', 1);
        $this->assertDatabaseHas('mobile_reading_entries', ['mobile_account_id' => $other->id]);
        $this->assertDatabaseHas('mobile_reading_operations', ['mobile_account_id' => $other->id]);
        $this->getJson('/api/mobile/reading-progress', $this->headers)->assertUnauthorized();
    }

    public function test_migration_can_be_reversed_without_removing_accounts(): void
    {
        (require database_path(self::MIGRATION))->down();
        $this->assertFalse(Schema::hasTable('mobile_reading_entries'));
        $this->assertFalse(Schema::hasTable('mobile_reading_operations'));
        $this->assertFalse(Schema::hasColumn('mobile_accounts', 'reading_progress_revision'));
        $this->assertDatabaseHas('mobile_accounts', ['id' => $this->account->id]);
        (require database_path(self::MIGRATION))->up();
        $this->batch([$this->operation()])->assertOk()->assertJsonPath('data.entries.0.revision', 1);
    }

    public function test_generated_limits_match_the_mobile_source_assets_when_available(): void
    {
        $athkarPath = base_path('../zabi/assets/athkar/catalog.json');
        $quranPath = base_path('../zabi/assets/quran/surah_list.json');
        if (! is_file($athkarPath) || ! is_file($quranPath)) {
            $this->markTestSkipped('Mobile sibling repository is not present in this checkout.');
        }
        $repetitions = ['' => 1, 'مرة واحدة' => 1, 'ثلاث مرات' => 3, 'سبع مرات' => 7, 'عشر مرات' => 10, 'ثلاثا وثلاثين مرة' => 33, 'مائة مرة' => 100];
        $athkar = [];
        foreach (json_decode(file_get_contents($athkarPath), true, 32, JSON_THROW_ON_ERROR)['categories'] as $category) {
            foreach ($category['entries'] as $entry) {
                $athkar[$entry['id']] = $repetitions[$entry['repetition']];
            }
        }
        $quran = [];
        foreach (json_decode(file_get_contents($quranPath), true, 32, JSON_THROW_ON_ERROR)['data'] as $chapter) {
            $quran[$chapter['id']] = (int) $chapter['verses_count'];
        }
        $this->assertSame($athkar, config('mobile_reading_items.athkar'));
        $this->assertSame($quran, config('mobile_reading_items.quran'));
    }
}
