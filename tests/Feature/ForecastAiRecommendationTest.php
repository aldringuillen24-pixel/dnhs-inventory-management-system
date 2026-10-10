<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ForecastAiRecommendation;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\ForecastAiRecommendationService;
use App\Services\StoredDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * The Recommendations tab covers every row in the forecast, so the invariants
 * here are about completeness rather than ranking:
 *
 *  1. Every forecast row appears in exactly one work queue, and the three badges
 *     sum to the row total. Nothing may be both "order this" and "no action",
 *     and nothing may silently vanish.
 *  2. Quantities come from the forecast, never from the provider. Only the
 *     sentences are written by a model, and each is validated on its own row.
 *  3. A cycle is written once. The cache is keyed on the forecast's generation
 *     stamp, so a retrain cannot serve the previous cycle's advice.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('forecast');

    foreach ([
        'forecast/forecast.json',
        'forecast/training-status.json',
    ] as $file) {
        $source = storage_path('app/'.$file);

        if (is_file($source)) {
            Storage::disk('forecast')->put($file, file_get_contents($source));
        }
    }

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $this->custodian = User::firstOrCreate(
        ['username' => 'recommendations-custodian'],
        [
            'role_id' => $role->role_id,
            'first_name' => 'Recommendations',
            'last_name' => 'Custodian',
            'email' => 'recommendations@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    // The stored forecast is validated against live inventory rows: every item
    // it names must exist with a matching id, category and name. Category ids
    // differ between the production database and this isolated one, so remap
    // them by name; predictions, history and confidence stay exactly as trained.
    $forecast = json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true);
    $rows = array_merge($forecast['forecasts'] ?? [], $forecast['insufficient_history'] ?? []);

    foreach ($rows as $index => $row) {
        $category = Category::firstOrCreate(
            ['category_name' => $row['category']],
            ['requires_serial_number' => false],
        );

        $rows[$index]['category_id'] = (int) $category->category_id;

        // Inventory::$fillable omits the item_id primary key, so the row is
        // written through forceFill.
        $item = Inventory::firstWhere('item_id', $row['inventory_id']) ?? new Inventory;

        $item->forceFill([
            'item_id' => $row['inventory_id'],
            'item_name' => $row['item_name'],
            'category_id' => $category->category_id,
            'unit' => $row['unit'] ?? 'piece',
            'user_id' => $this->custodian->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'status' => 'available',
            'date_acquired' => '2026-06-01',
        ])->save();
    }

    $forecast['forecasts'] = array_values(array_filter(
        $rows,
        fn (array $row): bool => ($row['status'] ?? '') === 'success',
    ));
    $forecast['insufficient_history'] = array_values(array_filter(
        $rows,
        fn (array $row): bool => ($row['status'] ?? '') !== 'success',
    ));

    Storage::disk('forecast')->put('forecast/forecast.json', json_encode($forecast));

    $this->forecastRows = app(StoredDemandForecastService::class)->read($this->custodian)['rows'];
    $this->provider = ['briefs' => [], 'chunks' => []];
});

/**
 * Fakes Gemini and records what each call asked for.
 *
 * The brief and the per-item chunks hit the same URL and are told apart by the
 * responseMimeType the caller requests, which is the only reliable signal.
 *
 * @param  array<int, array<int, array<string, string>>|null>  $chunkOverrides
 */
function fakeProvider(array &$state, array $chunkOverrides = [], ?string $briefText = null): void
{
    $state['briefs'] = [];
    $state['chunks'] = [];

    Http::fake(function ($request) use (&$state, $chunkOverrides, $briefText) {
        $body = json_decode((string) $request->body(), true);
        $isStructured = ($body['generationConfig']['responseMimeType'] ?? null) === 'application/json';

        if (! $isStructured) {
            $state['briefs'][] = $body;

            return Http::response(['candidates' => [['content' => ['parts' => [[
                'text' => $briefText ?? 'Many items are short this cycle and most of the pressure sits in one category. '
                    .'Some suggestions rest on thin history. The rest are already covered. Advisory only.',
            ]]]]]]);
        }

        $state['chunks'][] = $body;

        $facts = json_decode((string) ($body['contents'][0]['parts'][0]['text'] ?? ''), true);
        $items = is_array($facts) ? ($facts['items'] ?? []) : [];
        $index = count($state['chunks']) - 1;
        $override = $chunkOverrides[$index] ?? null;

        // An override may be a callable, so a test can build its reply from the
        // items the service actually sent rather than guessing their names.
        if (is_callable($override)) {
            $override = $override($items);
        }

        $reply = $override ?? array_map(
            fn (array $item): array => [
                'item_name' => $item['item_name'],
                'action' => 'Order '.$item['item_name'].' this cycle.',
            ],
            $items,
        );

        return Http::response(['candidates' => [['content' => ['parts' => [[
            'text' => is_string($reply) ? $reply : json_encode($reply),
        ]]]]]]);
    });
}

function recommend(array $payload = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->custodian)->postJson(
        route('api.custodian.reports.forecast.ai-recommendations'),
        $payload,
    );
}

/**
 * Picks a row that can actually reach Buy now and rewrites it into one.
 *
 * The fixture is uniformly Low confidence with a near-zero prediction, so no row
 * in it has a procurement gap and Buy now is empty. A demotion test needs a row
 * that genuinely sits in Buy now first, otherwise it would pass whether or not
 * the spike logic existed. Confidence and prediction are therefore set to values
 * the reader accepts, and the usage history supplied alongside.
 *
 * @return array{0: array<string, mixed>, 1: int} The rewritten payload and the id.
 */
function spikeCandidate(array $payload, array $usage, array $unknown = []): array
{
    $target = collect($payload['forecasts'] ?? [])->first()
        ?? collect($payload['insufficient_history'] ?? [])->first();

    $months = array_keys($usage);

    return [
        spikeRowInPayload($payload, (int) $target['inventory_id'], $usage, $unknown, [
            // Buy now requires High confidence and a real gap. The fixture
            // inventory quantity is 1, so any prediction above a handful is one.
            'confidence' => 'High',
            'predicted_quantity' => 200,
            'required_months' => 3,
            'status' => 'success',
        ]),
        (int) $target['inventory_id'],
    ];
}

/** Which queue a given inventory id landed in. */
function queueOf(array $payload, int $inventoryId): ?string
{
    foreach ($payload['queues'] as $queue) {
        foreach ($queue['items'] ?? [] as $item) {
            if ((int) ($item['inventory_id'] ?? 0) === $inventoryId) {
                return $queue['key'];
            }
        }
    }

    return null;
}

/**
 * Rewrites one row's usage history inside the stored forecast payload.
 *
 * `months_used` is kept in step with the usage map because the reader validates
 * that every mapped month is a used month and rejects a row where they disagree.
 *
 * @param  array<string, int|float>  $usage
 * @param  array<int, string>  $unknown
 * @return array<string, mixed>
 */
function spikeRowInPayload(array $payload, int $inventoryId, array $usage, array $unknown = [], array $override = []): array
{
    // `monthly_usage` holds the VERIFIED months only, and the reader requires
    // months_used to equal its keys exactly. An unknown month appears in
    // `unknown_months` alone and stretches the window, which must then be marked
    // incomplete -- the same rule the trainer writes.
    $all = array_values(array_unique([...array_keys($usage), ...$unknown]));
    sort($all);

    $end = $all[count($all) - 1];

    $history = [
        'history_window' => [
            'start_month' => $all[0],
            'end_month' => $end,
            'completeness' => $unknown === [] ? 'complete' : 'incomplete',
        ],
        'monthly_usage' => $usage,
        'unknown_months' => $unknown,
        'months_used' => array_keys($usage),
        'verified_months_used' => count($usage),
        // The reader requires forecast_month to be the month after the window
        // ends, so it follows the new window rather than the fixture's.
        'forecast_month' => Carbon::createFromFormat('!Y-m', $end)->addMonth()->format('Y-m'),
    ];

    $rewrite = function (array $row) use ($inventoryId, $history, $override): array {
        return (int) ($row['inventory_id'] ?? 0) === $inventoryId
            ? array_merge($row, $history, $override)
            : $row;
    };

    $payload['forecasts'] = array_map($rewrite, $payload['forecasts'] ?? []);
    $payload['insufficient_history'] = array_map($rewrite, $payload['insufficient_history'] ?? []);

    Storage::disk('forecast')->put('forecast/forecast.json', json_encode($payload));

    return $payload;
}

/** Every item across all three queues. */
function allItems(array $payload): array
{
    return collect($payload['queues'])
        ->flatMap(fn (array $queue): array => array_map(
            fn (array $item): array => array_merge($item, ['_queue' => $queue['key']]),
            $queue['items'],
        ))
        ->all();
}

test('the fixture forecast is live so the assertions below are meaningful', function () {
    expect($this->forecastRows)->not->toBeEmpty()
        ->and(collect($this->forecastRows)->where('needs_procurement', true))->not->toBeEmpty();
});

test('every forecast row appears in exactly one work queue', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();
    $items = allItems($payload);

    $expected = collect($this->forecastRows)->pluck('inventory_id')->map(fn ($id) => (int) $id)->sort()->values();
    $actual = collect($items)->pluck('inventory_id')->sort()->values();

    expect($actual->all())->toBe($expected->all(), 'Every forecast row must be recommended exactly once.')
        ->and($actual->duplicates()->all())->toBe([], 'A row may not appear in two queues.')
        ->and($items)->toHaveCount(count($this->forecastRows));
});

test('the three queue badges sum to the total item count', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();

    expect(collect($payload['queues'])->pluck('key')->all())
        ->toBe([
            ForecastAiRecommendationService::QUEUE_BUY_NOW,
            ForecastAiRecommendationService::QUEUE_VERIFY_FIRST,
            ForecastAiRecommendationService::QUEUE_NO_ACTION,
        ])
        ->and(collect($payload['queues'])->sum(fn (array $queue): int => $queue['item_count']))
        ->toBe($payload['item_count'])
        ->and($payload['item_count'])->toBe(count($this->forecastRows));
});

test('each queue badge matches the rows actually rendered under it', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();

    foreach ($payload['queues'] as $queue) {
        expect($queue['items'])->toHaveCount($queue['item_count']);
    }
});

test('a row with a gap lands in buy now or verify first, never in no action', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();
    $queued = collect(allItems($payload))->keyBy('inventory_id');

    foreach ($this->forecastRows as $row) {
        $item = $queued->get((int) $row['inventory_id']);

        expect($item)->not->toBeNull();

        $hasGap = ($row['needs_procurement'] ?? false) === true;
        $inNoAction = $item['_queue'] === ForecastAiRecommendationService::QUEUE_NO_ACTION;

        expect($hasGap)->not->toBe($inNoAction, "{$row['item_name']} gap={$hasGap} queue={$item['_queue']}");
    }
});

/*
 * Unusual consumption.
 *
 * The check is deliberately deterministic and deliberately conservative: a spike
 * demotes a row out of Buy now rather than adding a queue, so these tests assert
 * both the demotion and that the partition stayed intact.
 */

/**
 * Rewrites one row's monthly usage so the latest verified month spikes.
 *
 * Written through the stored forecast rather than a private method, so the test
 * exercises the same path production does: trainer output -> file -> reader.
 */
test('a usage spike moves a row out of buy now into verify first', function () {
    $flat = [
        '2026-04' => 10, '2026-05' => 10, '2026-06' => 10,
        '2026-07' => 10, '2026-08' => 10, '2026-09' => 10,
    ];

    // Control: flat history, High confidence, a real gap. Must sit in Buy now.
    [$payload, $id] = spikeCandidate(
        json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true),
        $flat,
    );

    fakeProvider($this->provider);
    $steady = recommend()->assertOk()->json();
    expect(queueOf($steady, $id))->toBe(ForecastAiRecommendationService::QUEUE_BUY_NOW);

    // Same row, same confidence, same prediction. Only the newest month moves.
    $spiked = $flat;
    $spiked['2026-09'] = 100;

    spikeRowInPayload($payload, $id, $spiked, [], [
        'confidence' => 'High',
        'predicted_quantity' => 200,
        'required_months' => 3,
        'status' => 'success',
    ]);

    fakeProvider($this->provider);
    $after = recommend(['refresh' => true])->assertOk()->json();

    expect(queueOf($after, $id))->toBe(ForecastAiRecommendationService::QUEUE_VERIFY_FIRST);
});

test('a spike demotion still leaves every row in exactly one queue', function () {
    fakeProvider($this->provider);
    $payload = recommend()->assertOk()->json();
    $items = allItems($payload);

    $expected = collect($this->forecastRows)->pluck('inventory_id')->map(fn ($id) => (int) $id)->sort()->values();
    $actual = collect($items)->pluck('inventory_id')->sort()->values();

    expect($actual->all())->toBe($expected->all())
        ->and($actual->duplicates()->all())->toBe([])
        ->and(collect($payload['queues'])->sum(fn (array $queue): int => $queue['item_count']))
        ->toBe($payload['item_count']);
});

test('too little verified history is reported as unknown rather than as a spike', function () {
    // Three months, the last 50x the first. Above required_months so the row is
    // still a valid estimate, but below the spike floor of six, so the honest
    // answer is "cannot tell" rather than a verdict.
    [$payload, $id] = spikeCandidate(
        json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true),
        ['2026-07' => 1, '2026-08' => 1, '2026-09' => 50],
    );

    fakeProvider($this->provider);
    $items = collect(allItems(recommend()->assertOk()->json()))->keyBy('inventory_id');

    expect($items[$id]['usage_spike'])->toBeFalse()
        ->and($items[$id]['usage_ratio'])->toBeNull();
});

test('a month the model reported as unknown is not read as zero usage', function () {
    // 2026-04 is unknown: the trainer has no events for it, and it must not be
    // treated as a zero-usage month. Six verified months of 10 with a 100 in the
    // last one is a real spike; had the unknown month been read as zero the
    // baseline itself would be wrong.
    [$payload, $id] = spikeCandidate(
        json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true),
        [
            '2026-05' => 10, '2026-06' => 10, '2026-07' => 10,
            '2026-08' => 10, '2026-09' => 10, '2026-10' => 100,
        ],
        ['2026-04'],
    );

    fakeProvider($this->provider);
    $items = collect(allItems(recommend()->assertOk()->json()))->keyBy('inventory_id');

    // Median of the five months before the last is 10, so the ratio is 10.0.
    expect($items[$id]['usage_spike'])->toBeTrue()
        ->and($items[$id]['usage_baseline'])->toEqual(10.0)
        ->and($items[$id]['usage_months_compared'])->toBe(6);
});

test('a flat history is never reported as a spike', function () {
    // The control case for the test above: same month count, no jump.
    [$payload, $id] = spikeCandidate(
        json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true),
        [
            '2026-04' => 10, '2026-05' => 10, '2026-06' => 10,
            '2026-07' => 10, '2026-08' => 10, '2026-09' => 10,
        ],
    );

    fakeProvider($this->provider);
    $items = collect(allItems(recommend()->assertOk()->json()))->keyBy('inventory_id');

    expect($items[$id]['usage_spike'])->toBeFalse()
        // toEqual, not toBe: the ratio survives a JSON round trip through the response,
        // where PHP hands back an int where the service computed a float.
        ->and($items[$id]['usage_ratio'])->toEqual(1.0);
});

test('the fallback sentence explains the spike without a provider', function () {
    spikeCandidate(
        json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true),
        [
            '2026-04' => 10, '2026-05' => 10, '2026-06' => 10,
            '2026-07' => 10, '2026-08' => 10, '2026-09' => 60,
        ],
    );

    // No key and no fake provider: this is the local template path.
    config(['services.gemini.api_key' => null]);

    $items = collect(allItems(recommend(['refresh' => true])->assertOk()->json()))
        ->keyBy('inventory_id');

    $spiked = collect($items)->firstWhere('usage_spike', true);

    expect($spiked)->not->toBeNull()
        ->and($spiked['action'])->toContain('far above')
        ->and($spiked['usage_ratio'])->toEqual(6.0);
});

test('spike figures are sent to the provider as approved facts', function () {
    [$payload, $id] = spikeCandidate(
        json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true),
        [
            '2026-04' => 10, '2026-05' => 10, '2026-06' => 10,
            '2026-07' => 10, '2026-08' => 10, '2026-09' => 60,
        ],
    );

    fakeProvider($this->provider);
    recommend(['refresh' => true])->assertOk();

    // The recorder stores the whole Gemini request, so the facts this layer sent
    // are the JSON string in the prompt part rather than a top-level key.
    $sent = collect($this->provider['chunks'])
        ->map(fn (array $chunk): array => json_decode(
            (string) ($chunk['contents'][0]['parts'][0]['text'] ?? ''),
            true,
        ) ?? [])
        ->flatMap(fn (array $facts): array => $facts['items'] ?? [])
        ->firstWhere('inventory_id', $id);

    expect($sent)->not->toBeNull()
        ->and($sent['usage_spike'])->toBeTrue()
        ->and($sent['usage_ratio'])->toEqual(6.0)
        ->and($sent['usage_baseline'])->toEqual(10.0)
        ->and($sent['action_hint'])->toBe('usage_spike');
});

test('a provider sentence quoting the approved ratio is not rejected', function () {
    [$payload, $id] = spikeCandidate(
        json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true),
        [
            '2026-04' => 10, '2026-05' => 10, '2026-06' => 10,
            '2026-07' => 10, '2026-08' => 10, '2026-09' => 60,
        ],
    );

    // Echoes the ratio back. Without the ratio in the approved set the grounding
    // check reads "6" as invented and silently drops the sentence to the template.
    fakeProvider($this->provider, [
        fn (array $items): array => array_map(fn (array $item): array => [
            'item_name' => $item['item_name'],
            'action' => ($item['usage_spike'] ?? false) === true
                ? sprintf(
                    '%s is running at %sx its usual rate. Confirm the real rate before ordering.',
                    $item['item_name'],
                    $item['usage_ratio'],
                )
                : 'Order '.$item['item_name'].' this cycle.',
        ], $items),
    ]);

    $items = collect(allItems(recommend(['refresh' => true])->assertOk()->json()))
        ->keyBy('inventory_id');

    expect($items[$id]['action'])->toContain('usual rate');
});

test('quantities come from the forecast rather than the provider', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();
    $rendered = collect(allItems($payload))->keyBy('inventory_id');

    foreach ($this->forecastRows as $row) {
        $item = $rendered->get((int) $row['inventory_id']);

        expect((int) $item['suggested_procurement'])->toBe((int) ($row['suggested_procurement'] ?? 0))
            ->and((int) $item['forecast_demand'])->toBe((int) ($row['forecast_demand'] ?? 0))
            ->and($item['priority'])->toBe($row['priority'] ?? null);
    }
});

test('a row with too little history is never given a suggested quantity', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();

    $withoutEstimate = collect(allItems($payload))
        ->filter(fn (array $item): bool => ($item['status'] ?? null) !== 'success');

    if ($withoutEstimate->isEmpty()) {
        $this->markTestSkipped('Fixture forecast has no insufficient-history rows.');
    }

    expect($withoutEstimate->every(fn (array $item): bool => $item['suggested_procurement'] === null
        && $item['forecast_demand'] === null
        && $item['action'] !== ''))->toBeTrue();

    // Such a row has nothing to verify, so it belongs with the soft suggestions.
    expect($withoutEstimate->every(fn (array $item): bool => $item['_queue'] !== ForecastAiRecommendationService::QUEUE_BUY_NOW))->toBeTrue();
});

test('every recommended item carries a sentence and never claims an order was placed', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();

    foreach (allItems($payload) as $item) {
        expect($item['action'])->toBeString()->not->toBe('')
            ->and(strlen($item['action']))->toBeLessThanOrEqual(240);

        expect(preg_match('/(?:₱|\bphp\b|\bphp\s+\d)/iu', $item['action']))->toBe(0, 'No invented cost may reach a row.');
        expect(preg_match('/\b(?:placed|approved)\s+(?:the\s+|a\s+)?(?:purchase\s+)?order\b/iu', $item['action']))
            ->toBe(0, 'No row may claim an order was placed.');
    }
});

test('the brief never states a price or claims an order was created', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();

    expect($payload['brief'])->toBeString()->not->toBe('')
        ->and(preg_match('/(?:₱|\bphp\b)/iu', $payload['brief']))->toBe(0)
        ->and(preg_match('/\b(?:placed|approved)\s+(?:the\s+|a\s+)?(?:purchase\s+)?order\b/iu', $payload['brief']))->toBe(0);
});

test('a brief that names no item is still accepted', function () {
    // A cycle brief is about counts, so requiring an item name would reject a
    // correct summary. This pins that exemption.
    fakeProvider($this->provider, [], 'Many items are short this cycle and most of the pressure sits in one category. Advisory only.');

    $payload = recommend()->assertOk()->json();

    expect($payload['source'])->toBe('provider')
        ->and($payload['provider_status'])->toBe('ok');
});

test('a brief citing a figure the forecast did not calculate is replaced', function () {
    // 98765 appears nowhere in the facts, so the reply is ungrounded.
    fakeProvider($this->provider, [], 'You need to order 98765 units this cycle. Advisory only.');

    $payload = recommend()->assertOk()->json();

    expect($payload['source'])->toBe('local')
        ->and($payload['provider_status'])->toBe('reply_rejected')
        ->and($payload['brief'])->not->toContain('98765');
});

test('one invented cost costs only that row its written sentence', function () {
    // Grounding is per row, not per chunk, so a single fabricated peso figure
    // must not discard its neighbours from the same provider reply. Both rows
    // below come from the same chunk.
    $rejected = 'Order this at a budget of 4500 per unit.';
    $accepted = 'Confirm the current count before deciding.';

    fakeProvider($this->provider, [
        fn (array $items): array => [
            ['item_name' => $items[0]['item_name'], 'action' => $rejected],
            ['item_name' => $items[1]['item_name'], 'action' => $accepted],
        ],
    ]);

    $payload = recommend()->assertOk()->json();

    $facts = json_decode((string) $this->provider['chunks'][0]['contents'][0]['parts'][0]['text'], true);
    $sent = collect($facts['items'])->values();

    expect($sent->count())->toBeGreaterThan(1);

    $rendered = collect(allItems($payload))->keyBy('inventory_id');
    $badId = $sent[0]['inventory_id'];
    $goodId = $sent[1]['inventory_id'];

    // The offending row falls back to the template...
    expect($rendered->get((int) $badId)['action'])->not->toBe($rejected)
        ->and($rendered->get((int) $badId)['action'])->not->toContain('4500')
        ->and($rendered->get((int) $badId)['action'])->not->toBe('');

    // ...while its neighbour keeps the sentence the provider wrote.
    expect($rendered->get((int) $goodId)['action'])->toBe($accepted);
});

test('a provider reply naming an item that was not supplied is discarded', function () {
    fakeProvider($this->provider, [[
        ['item_name' => 'Ink Fountain Pen With Nib', 'action' => 'Order this immediately.'],
    ]]);

    $payload = recommend()->assertOk()->json();
    $names = collect(allItems($payload))->pluck('item_name');

    expect($names->contains('Ink Fountain Pen With Nib'))->toBeFalse();
});

test('the whole forecast is covered across several provider chunks', function () {
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();

    expect($this->provider['briefs'])->toHaveCount(1)
        ->and($this->provider['chunks'])->not->toBeEmpty();

    $chunkSize = ForecastAiRecommendationService::MAX_CHUNK_ITEMS;
    $actionable = count(array_filter(
        allItems($payload),
        fn (array $item): bool => $item['_queue'] !== ForecastAiRecommendationService::QUEUE_NO_ACTION,
    ));

    expect(count($this->provider['chunks']))->toBe((int) ceil($actionable / $chunkSize));

    // No chunk may exceed the configured slice size.
    foreach ($this->provider['chunks'] as $call) {
        $facts = json_decode((string) $call['contents'][0]['parts'][0]['text'], true);
        expect(count($facts['items']))->toBeLessThanOrEqual($chunkSize);
    }
});

test('the no action queue is not sent to the provider', function () {
    // Every row in it carries the same sentence, so asking a model to write it
    // repeatedly buys nothing. Only the actionable queues are sent.
    fakeProvider($this->provider);

    $payload = recommend()->assertOk()->json();
    $noActionIds = collect($payload['queues'])
        ->firstWhere('key', ForecastAiRecommendationService::QUEUE_NO_ACTION)['items'] ?? [];
    $sentIds = collect($this->provider['chunks'])
        ->flatMap(function (array $call): array {
            $facts = json_decode((string) $call['contents'][0]['parts'][0]['text'], true);

            return array_map(fn (array $item): int => (int) $item['inventory_id'], $facts['items'] ?? []);
        });

    foreach ($noActionIds as $item) {
        expect($sentIds->contains((int) $item['inventory_id']))->toBeFalse();
    }
});

test('the second read is served from the saved cycle without calling the provider', function () {
    fakeProvider($this->provider);

    $first = recommend()->assertOk()->json();
    $callsAfterFirst = count($this->provider['briefs']) + count($this->provider['chunks']);

    $second = recommend()->assertOk()->json();

    expect($callsAfterFirst)->toBeGreaterThan(0)
        ->and(count($this->provider['briefs']) + count($this->provider['chunks']))->toBe($callsAfterFirst)
        ->and($second['cached'])->toBeTrue()
        ->and($second['brief'])->toBe($first['brief'])
        ->and(collect(allItems($second))->pluck('action')->all())
        ->toBe(collect(allItems($first))->pluck('action')->all());
});

test('refresh rewrites the cycle instead of reading the saved one', function () {
    fakeProvider($this->provider);

    $first = recommend()->assertOk()->json();
    $callsAfterFirst = count($this->provider['briefs']) + count($this->provider['chunks']);

    $refreshed = recommend(['refresh' => true])->assertOk()->json();

    expect($refreshed['cached'])->toBeFalse()
        ->and(count($this->provider['briefs']) + count($this->provider['chunks']))->toBeGreaterThan($callsAfterFirst);
});

test('a retrained forecast is not served the previous cycle advice', function () {
    fakeProvider($this->provider);

    $first = recommend()->assertOk()->json();

    // The payload's own generation stamp is the cache key, so a new stamp is a
    // new cycle and must be written from scratch. Kept recent on purpose: a
    // stamp past forecast.maximum_age_hours is rejected as stale, which would
    // test staleness rather than the cache key.
    $forecast = json_decode((string) Storage::disk('forecast')->get('forecast/forecast.json'), true);
    $forecast['generated_at'] = now()->subHour()->toIso8601String();
    Storage::disk('forecast')->put('forecast/forecast.json', json_encode($forecast));

    $this->forecastRows = app(StoredDemandForecastService::class)->read($this->custodian)['rows'];
    $second = recommend()->assertOk()->json();

    expect($second['cached'])->toBeFalse()
        ->and($second['forecast_generated_at'])->not->toBe($first['forecast_generated_at'])
        ->and(ForecastAiRecommendation::query()->count())->toBe(2);
});

test('reading twice leaves exactly one saved cycle', function () {
    fakeProvider($this->provider);

    recommend()->assertOk();
    recommend()->assertOk();

    expect(ForecastAiRecommendation::query()->count())->toBe(1);
});

test('only the written sentence is saved, never a quantity', function () {
    fakeProvider($this->provider);

    recommend()->assertOk();

    $record = ForecastAiRecommendation::query()->sole();

    expect($record->queues)->toBeArray()->not->toBeEmpty();

    foreach ($record->queues as $queue) {
        foreach ($queue['items'] as $item) {
            expect(array_keys($item))->toBe(['inventory_id', 'action']);
        }
    }
});

/**
 * The sentences the local template produces, one per licensed action hint.
 *
 * Asserted as an exact set rather than "not empty", because the point is that a
 * missing provider yields the SAME small fixed vocabulary for every row, not a
 * unique line per item.
 */
const LOCAL_ACTIONS = [
    'Too little verified history to estimate. Confirm recent usage before ordering anything.',
    'This figure rests on thin history and staff are waiting. Confirm usage before committing.',
    'Confirm actual usage before committing to this quantity.',
    'Staff requested this while it was unavailable. Order for them this cycle.',
    'Stock already covers next month, but staff requested this while it was unavailable.',
    'Stock already covers next month. No action needed this cycle.',
];

test('with no provider key every row still gets a complete sentence', function () {
    config()->set('services.gemini.api_key', null);

    $payload = recommend()->assertOk()->json();
    $actions = collect(allItems($payload))->pluck('action')->unique();

    expect($payload['source'])->toBe('local')
        ->and($payload['provider_status'])->toBe('no_key')
        ->and($actions)->not->toBeEmpty()
        // Every sentence comes from the fixed template set, so no provider text
        // reached the tab and no row was left blank.
        ->and($actions->diff(LOCAL_ACTIONS)->all())->toBe([]);
});

test('an unreachable provider degrades to the same complete answer', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Timed out'));

    $payload = recommend()->assertOk()->json();

    expect(collect(allItems($payload))->every(fn (array $item): bool => trim($item['action']) !== ''))->toBeTrue()
        ->and($payload['brief'])->toBeString()->not->toBe('');
});

test('a provider that returns unusable text does not leave any row blank', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => 'not json at all']]]]],
    ])]);

    $payload = recommend()->assertOk()->json();

    expect(collect(allItems($payload))->every(fn (array $item): bool => trim($item['action']) !== ''))->toBeTrue();
});

test('recommendations are refused for a role without procurement capability', function () {
    fakeProvider($this->provider);

    $headRole = Role::firstOrCreate(['role_name' => 'School Head']);
    $head = User::firstOrCreate(
        ['username' => 'recommendations-head'],
        [
            'role_id' => $headRole->role_id,
            'first_name' => 'School',
            'last_name' => 'Head',
            'email' => 'recommendations-head@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    $this->actingAs($head)
        ->postJson(route('api.custodian.reports.forecast.ai-recommendations'))
        ->assertForbidden();

    // A refusal must not have written anything.
    expect(ForecastAiRecommendation::query()->count())->toBe(0);
});

test('recommendations require authentication', function () {
    $this->postJson(route('api.custodian.reports.forecast.ai-recommendations'))
        ->assertUnauthorized();
});

test('no trained forecast is refused rather than answered from another source', function () {
    Storage::disk('forecast')->put('forecast/forecast.json', json_encode([
        'schema_version' => 2,
        'source_type' => 'live',
        'model_version' => 'demand-linear-regression-v2',
        'generated_at' => '2026-10-01T00:00:00+00:00',
        'forecasts' => [],
        'insufficient_history' => [],
    ]));

    fakeProvider($this->provider);

    recommend()->assertStatus(422);

    expect($this->provider['briefs'])->toBe([])->and($this->provider['chunks'])->toBe([]);
});
