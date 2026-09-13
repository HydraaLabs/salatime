<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Mobile\MobileAccount;
use DateTimeImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use stdClass;

class ReadingProgressController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $query = $request->query();
        if (array_diff(array_keys($query), ['after', 'limit'])) {
            $this->invalid('query', 'Unknown query field.');
        }
        $after = $this->queryInteger($query['after'] ?? '0', 'after', 0, 9007199254740991);
        $limit = $this->queryInteger($query['limit'] ?? '500', 'limit', 1, 500);
        // One snapshot query: subsequent updates receive a newer revision and
        // remain discoverable even when a row moves past this page's cursor.
        $rows = DB::table('mobile_reading_entries')
            ->where('mobile_account_id', $request->user()->id)
            ->where('revision', '>', $after)->orderBy('revision')->limit($limit + 1)->get();
        $entries = $rows->take($limit)->map(fn ($row) => $this->entry($row))->values()->all();

        return response()->json(['data' => [
            'entries' => $entries,
            'cursor' => $entries ? $entries[count($entries) - 1]['revision'] : $after,
            'hasMore' => $rows->count() > $limit,
        ]]);
    }

    public function batch(Request $request): JsonResponse
    {
        $operations = $this->operations($request);

        return DB::transaction(function () use ($request, $operations) {
            // Shared with preferences/account mutations; the account row exists
            // before any reading data, so first writes are serialized as well.
            $account = MobileAccount::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $revision = (int) $account->reading_progress_revision;
            $affected = [];
            $acknowledged = [];
            foreach ($operations as $operation) {
                $identity = [
                    'mobile_account_id' => $account->id,
                    'kind' => $operation['kind'],
                    'item_key' => $operation['itemKey'],
                    'day' => $operation['day'],
                ];
                $stored = DB::table('mobile_reading_operations')
                    ->where('mobile_account_id', $account->id)
                    ->where('operation_id', strtolower($operation['id']))->first();
                if ($stored !== null) {
                    $existing = DB::table('mobile_reading_entries')->where($identity)->where('id', $stored->entry_id)->first();
                    if ($existing === null || (int) $stored->count !== $operation['count']) {
                        // Throw to roll back earlier operations in this batch.
                        throw new HttpResponseException(response()->json([
                            'message' => 'An operation identifier cannot be reused for a different change.',
                            'code' => 'reading_operation_conflict',
                        ], 409));
                    }
                    $affected[(int) $stored->entry_id] = true;
                } else {
                    $revision++;
                    $now = now();
                    $entryId = DB::table('mobile_reading_entries')->where($identity)->value('id');
                    $values = ['count' => $operation['count'], 'revision' => $revision, 'updated_at' => $now];
                    if ($entryId === null) {
                        $entryId = DB::table('mobile_reading_entries')->insertGetId($identity + $values + ['created_at' => $now]);
                    } else {
                        DB::table('mobile_reading_entries')->where($identity)->update($values);
                    }
                    DB::table('mobile_reading_operations')->insert([
                        'mobile_account_id' => $account->id,
                        'operation_id' => strtolower($operation['id']),
                        'entry_id' => $entryId,
                        'count' => $operation['count'],
                        'revision' => $revision,
                        'created_at' => $now,
                    ]);
                    $affected[(int) $entryId] = true;
                }
                $acknowledged[] = $operation['id'];
            }
            DB::table('mobile_accounts')->where('id', $account->id)->update(['reading_progress_revision' => $revision]);
            $entries = DB::table('mobile_reading_entries')->where('mobile_account_id', $account->id)
                ->whereIn('id', array_keys($affected))->orderBy('revision')->get()
                ->map(fn ($row) => $this->entry($row))->all();

            // A POST must not advance the client's incremental GET cursor:
            // other devices may have changed entries outside this batch.
            return response()->json(['data' => [
                'acknowledged' => array_values(array_unique($acknowledged)),
                'entries' => $entries,
            ]]);
        }, 3);
    }

    private function operations(Request $request): array
    {
        if (! $request->isJson()) {
            $this->invalid('operations', 'A JSON object is required.');
        }
        try {
            // Inspect original JSON to preserve integer types and canonical IDs;
            // global string-trimming middleware must not normalize an operation.
            $body = json_decode($request->getContent(), false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->invalid('operations', 'Invalid JSON.');
        }
        if (! $body instanceof stdClass || array_keys(get_object_vars($body)) !== ['operations'] || ! is_array($body->operations)) {
            $this->invalid('operations', 'Only an operations array is allowed.');
        }
        if (count($body->operations) < 1 || count($body->operations) > 100) {
            $this->invalid('operations', 'Provide between 1 and 100 operations.');
        }
        $operations = [];
        $athkar = config('mobile_reading_items.athkar');
        $quran = config('mobile_reading_items.quran');
        foreach ($body->operations as $index => $raw) {
            $key = 'operations.'.$index;
            if (! $raw instanceof stdClass) {
                $this->invalid($key, 'An operation must be an object.');
            }
            $operation = get_object_vars($raw);
            $fields = ['id', 'kind', 'itemKey', 'day', 'count'];
            if (count($operation) !== count($fields) || array_diff(array_keys($operation), $fields)) {
                $this->invalid($key, 'Only id, kind, itemKey, day and count are allowed.');
            }
            if (! is_string($operation['id']) || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $operation['id'])) {
                $this->invalid($key.'.id', 'A UUID is required.');
            }
            if (! in_array($operation['kind'], ['athkar', 'quran'], true)) {
                $this->invalid($key.'.kind', 'Unknown reading kind.');
            }
            if (! is_string($operation['itemKey'])) {
                $this->invalid($key.'.itemKey', 'A canonical reading identifier is required.');
            }
            $item = $operation['itemKey'];
            if ($operation['kind'] === 'athkar') {
                $target = $athkar[$item] ?? null;
                if ($target === null) {
                    $this->invalid($key.'.itemKey', 'Unknown Athkar identifier.');
                }
            } else {
                if (! preg_match('/\A([1-9][0-9]{0,2}):([1-9][0-9]{0,2})\z/', $item, $parts)
                    || ! isset($quran[(int) $parts[1]]) || (int) $parts[2] > $quran[(int) $parts[1]]) {
                    $this->invalid($key.'.itemKey', 'Unknown Quran verse.');
                }
                $target = 1;
            }
            $day = $operation['day'];
            $date = is_string($day) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $day)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $day) : false;
            if ($date === false || $date->format('Y-m-d') !== $day || $day < '2000-01-01' || $day > '2100-12-31') {
                $this->invalid($key.'.day', 'Use a valid date from 2000-01-01 through 2100-12-31.');
            }
            if (! is_int($operation['count']) || $operation['count'] < 0 || $operation['count'] > $target) {
                $this->invalid($key.'.count', 'The integer count is outside this reading target.');
            }
            $operations[] = $operation;
        }

        return $operations;
    }

    private function queryInteger(mixed $value, string $key, int $min, int $max): int
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/\A(?:0|[1-9][0-9]{0,15})\z/', (string) $value)
            || (int) $value < $min || (int) $value > $max) {
            $this->invalid($key, 'Invalid pagination value.');
        }

        return (int) $value;
    }

    private function entry(object $row): array
    {
        return ['kind' => $row->kind, 'itemKey' => $row->item_key, 'day' => $row->day, 'count' => (int) $row->count, 'revision' => (int) $row->revision];
    }

    private function invalid(string $key, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [$key => [$message]],
        ], 422));
    }
}
