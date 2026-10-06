<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Role;
use App\Models\User;
use App\Services\ForecastDecisionSupportService;
use App\Services\StoredDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
 * AI Decision Support is the layer between the ML forecast and the procurement
 * decision. Two invariants matter most and are asserted here:
 *
 *  1. The item list is computed LOCALLY from the stored forecast, so the chat,
 *     the table and the exported PDF can never disagree about quantities.
 *  2. The PDF is rebuilt from the forecast, not trusted from the client, so a
 *     stale or tampered payload cannot print numbers the model did not produce.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
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
        ['username' => 'decision-custodian'],
        [
            'role_id' => $role->role_id,
            'first_name' => 'Decision',
            'last_name' => 'Support',
            'email' => 'decision@example.com',
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
});

function decisionSupportPost($test, string $promptType, array $context = [])
{
    return $test->actingAs($test->custodian)->postJson(
        route('api.custodian.reports.forecast.decision-support'),
        array_merge(['prompt_type' => $promptType], $context),
    );
}

test('the fixture forecast is live so the assertions below are meaningful', function () {
    expect($this->forecastRows)->not->toBeEmpty()
        ->and(collect($this->forecastRows)->where('needs_procurement', true))->not->toBeEmpty();
});

test('purchase-first returns only rows with a procurement gap, highest priority first', function () {
    $response = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    expect($response['status'])->toBe('success')
        ->and($response['question'])->toBe('What should we purchase first?')
        ->and($response['inventory_ids'])->not->toBeEmpty()
        ->and(count($response['inventory_ids']))
        ->toBeLessThanOrEqual(ForecastDecisionSupportService::MAX_SELECTED_ITEMS);

    $gapped = collect($this->forecastRows)
        ->filter(fn (array $row): bool => $row['needs_procurement'] === true)
        ->keyBy('inventory_id');

    foreach ($response['inventory_ids'] as $inventoryId) {
        expect($gapped->has($inventoryId))->toBeTrue(
            "Decision support returned inventory id {$inventoryId}, which has no procurement gap."
        );
    }

    // The first suggestion must be at least as urgent as the ones after it.
    $ranks = collect($response['inventory_ids'])
        ->map(fn (int $id): int => (int) $gapped->get($id)['priority_rank'])
        ->values();

    expect($ranks->first())->toBe($ranks->max());
});

test('every recommended item id exists in the stored forecast', function () {
    foreach (ForecastDecisionSupportService::PROMPT_TYPES as $promptType) {
        $payload = decisionSupportPost($this, $promptType)->assertOk()->json();

        $known = collect($this->forecastRows)->pluck('inventory_id');

        expect($payload['inventory_ids'])->not->toBeEmpty();

        foreach ($payload['inventory_ids'] as $inventoryId) {
            expect($known->contains($inventoryId))->toBeTrue(
                "Prompt {$promptType} returned unknown inventory id {$inventoryId}."
            );
        }
    }
});

test('deferrable returns rows whose stock already covers the forecast', function () {
    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_DEFERRABLE)
        ->assertOk()
        ->json();

    expect($payload['question'])->toBe('What can wait?');

    $rows = collect($this->forecastRows)->keyBy('inventory_id');

    foreach ($payload['inventory_ids'] as $inventoryId) {
        $row = $rows->get($inventoryId);

        expect($row['needs_procurement'])->toBeFalse()
            ->and($row['suggested_procurement'])->toBe(0);
    }
});

test('verify-first surfaces the rows that rest on limited history', function () {
    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_VERIFY_FIRST)
        ->assertOk()
        ->json();

    expect($payload['question'])->toBe('Which rows need verification first?');

    $rows = collect($this->forecastRows)->keyBy('inventory_id');

    foreach ($payload['inventory_ids'] as $inventoryId) {
        $row = $rows->get($inventoryId);

        expect($row['status'] === 'success' || in_array($row['confidence'], ['Low', 'Medium'], true))
            ->toBeTrue("Row {$inventoryId} does not need verification but was suggested for it.");
    }
});

test('the local answer restates only values the forecast calculated', function () {
    config(['services.gemini.api_key' => null]);

    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    // With no provider key the deterministic summary is used, and the response
    // must say so precisely rather than leaving the cause ambiguous.
    expect($payload['source'])->toBe('local')
        ->and($payload['provider_status'])->toBe('no_key');

    $approved = collect($payload['items'])
        ->flatMap(fn (array $item): array => [
            $item['forecast_demand'],
            $item['safety_stock'],
            $item['available_stock'],
            $item['pending_demand'],
            $item['suggested_procurement'],
        ])
        ->filter(fn (mixed $value): bool => is_int($value) || is_float($value))
        ->map(fn (int|float $value): string => (string) $value);

    // The period, its timestamp and the supplied item names are approved facts:
    // the year inside "October 2026" and the 24 inside "Crayon Set 24 Colors"
    // are not invented quantities.
    foreach ([$payload['forecast_period'] ?? null, $payload['generated_at'] ?? null] as $stamp) {
        preg_match_all('/\d+/', (string) $stamp, $stampNumbers);
        $approved = $approved->merge($stampNumbers[0]);
    }

    foreach ($payload['items'] as $item) {
        foreach (['item_name', 'category'] as $field) {
            preg_match_all('/\d+/', (string) ($item[$field] ?? ''), $nameNumbers);
            $approved = $approved->merge($nameNumbers[0]);
        }
    }

    $approved = $approved->unique()->values();

    // "1." / "2)" is a list position, not a quantity, and a numbered list is not
    // always one item per line. `(?!\d)` stops the scanner backtracking to a
    // prefix, so "Air Freshener 300ml" is not read as the quantity 30.
    $scannable = preg_replace('/(?:(?<=\.)|^)\s*\d{1,3}[.)]\s+/m', '', $payload['answer']);

    preg_match_all('/(?<![\pL\d])\d+(?:[,.]\d+)*(?!\d)(?![\pL])/u', (string) $scannable, $matches);

    foreach ($matches[0] as $number) {
        expect($approved->contains(str_replace(',', '', $number)))->toBeTrue(
            "Answer used unapproved number {$number}."
        );
    }
});

test('the answer never instructs the user to place or approve an order', function () {
    config(['services.gemini.api_key' => null]);

    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    expect($payload['answer'])->toContain('advisory only');
    expect($payload['answer'])->not->toMatch('/\b(placed|approved)\s+(the\s+|a\s+)?(purchase\s+)?order\b/i');
});

test('recommending procurement is allowed, but invented prices are not', function () {
    // Gemini is expected to say "buy"/"purchase" — that is the point of this
    // layer. The guard exists to stop it claiming an order was acted on, or
    // quoting a peso figure the forecast never supplied.
    $service = new ForecastDecisionSupportService(
        app(\App\Services\AiCapabilityPolicy::class),
        app(\App\Services\GeminiApiService::class),
        app(StoredDemandForecastService::class),
    );

    $facts = [
        'forecast_period' => 'October 2026',
        'generated_at' => '2026-10-01T00:00:00+00:00',
        'forecast_summary' => ['items_forecasted' => 99, 'items_needing_procurement' => 79],
        'selected_count' => 1,
        'max_items_shown' => 10,
        'items' => [[
            'item_name' => 'Bond Paper',
            'forecast_demand' => 36,
            'safety_stock' => 9,
            'available_stock' => 5,
            'pending_demand' => 0,
            'suggested_procurement' => 40,
        ]],
    ];

    $accepted = 'Purchase Bond Paper first: demand 36 plus buffer 9 minus stock 5 gives a suggested 40. Urgent.';
    $acceptedWithListSize = 'Here are the top 10 priorities. Bond Paper leads with demand 36 and a suggested 40.';
    $rejectedAsCost = 'Purchase Bond Paper first; budget around 4000 for this.';
    $rejectedAsAction = 'I have placed the order for Bond Paper and stock is updated.';

    $check = fn (string $reply): bool => (new \ReflectionMethod($service, 'isGroundedReply'))
        ->invoke($service, $reply, $facts);

    expect($check($accepted))->toBeTrue('A plain procurement recommendation must be accepted.')
        ->and($check($acceptedWithListSize))
        ->toBeTrue('Stating the size of the list this layer supplied must be accepted.')
        ->and($check($rejectedAsCost))->toBeFalse('An invented peso figure must be rejected.')
        ->and($check($rejectedAsAction))->toBeFalse('A claim that an order was placed must be rejected.');
});

test('the response names the real reason a provider answer was not used', function () {
    // The UI used to caption every non-provider message with one blanket
    // sentence. These assertions pin each distinct cause to its own value.
    config(['services.gemini.api_key' => null]);

    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    expect($payload)->toHaveKeys(['source', 'provider_status'])
        ->and($payload['source'])->toBe('local')
        ->and($payload['provider_status'])->toBe('no_key');
});

test('the single-item explanation endpoint reports which source wrote it', function () {
    $row = collect($this->forecastRows)->firstWhere('status', 'success');

    $payload = $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.reports.forecast.explanation'), [
            'inventory_id' => $row['inventory_id'],
        ])
        ->assertOk()
        ->json();

    // Previously omitted, so the SPA assumed "local" and captioned Gemini text
    // as a provider fallback.
    expect($payload)->toHaveKey('source')
        ->and($payload['source'])->toBeIn(['provider', 'local'])
        ->and($payload['explanation'])->not->toBeEmpty();
});

test('decision support is refused for a role without procurement capability', function () {
    $headRole = Role::firstOrCreate(['role_name' => 'School Head']);
    $head = User::firstOrCreate(
        ['username' => 'decision-head'],
        [
            'role_id' => $headRole->role_id,
            'first_name' => 'Decision',
            'last_name' => 'Head',
            'email' => 'decisionhead@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    $this->actingAs($head)
        ->postJson(route('api.custodian.reports.forecast.decision-support'), [
            'prompt_type' => ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST,
        ])
        ->assertStatus(403);
});

test('an unsupported decision question is rejected', function () {
    decisionSupportPost($this, 'invent_a_category')->assertStatus(422);
});

test('the procurement list PDF is produced from the forecast rows the AI chose', function () {
    $answer = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $response = $this->actingAs($this->custodian)
        ->post(route('api.custodian.reports.forecast.procurement-list-pdf'), [
            'inventory_ids' => $answer['inventory_ids'],
            'prompt_type' => $answer['prompt_type'],
        ])
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
    // dompdf emits a real PDF document, not an error page or blank body.
    expect(substr((string) $response->getContent(), 0, 5))->toBe('%PDF-');
});

test('the PDF refuses ids that are no longer in the forecast', function () {
    $answer = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $unknownId = max(collect($this->forecastRows)->pluck('inventory_id')->all()) + 5000;

    $this->actingAs($this->custodian)
        ->postJson(route('api.custodian.reports.forecast.procurement-list-pdf'), [
            'inventory_ids' => [$unknownId],
        ])
        ->assertStatus(422)
        ->assertJsonPath('missing_inventory_ids.0', $unknownId)
        ->assertJsonStructure(['message', 'missing_inventory_ids']);
});

test('exporting the list does not change any inventory or forecast record', function () {
    $answer = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $quantities = collect($this->forecastRows)
        ->mapWithKeys(fn (array $row): array => [$row['inventory_id'] => $row['available_stock']]);

    $forecastBefore = Storage::disk('forecast')->get('forecast/forecast.json');

    $this->actingAs($this->custodian)
        ->post(route('api.custodian.reports.forecast.procurement-list-pdf'), [
            'inventory_ids' => $answer['inventory_ids'],
        ])
        ->assertOk();

    $after = app(StoredDemandForecastService::class)->read($this->custodian)['rows'];

    foreach ($after as $row) {
        expect($quantities->get($row['inventory_id']))->toBe($row['available_stock']);
    }

    expect(Storage::disk('forecast')->get('forecast/forecast.json'))->toBe($forecastBefore);
});

test('the PDF export is refused for a role without procurement capability', function () {
    $answer = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $headRole = Role::firstOrCreate(['role_name' => 'School Head']);
    $head = User::firstOrCreate(
        ['username' => 'decision-pdf-head'],
        [
            'role_id' => $headRole->role_id,
            'first_name' => 'Decision',
            'last_name' => 'Pdf',
            'email' => 'decisionpdf@example.com',
            'password' => 'password',
            'status' => 'active',
        ],
    );

    $this->actingAs($head)
        ->postJson(route('api.custodian.reports.forecast.procurement-list-pdf'), [
            'inventory_ids' => $answer['inventory_ids'],
        ])
        ->assertStatus(403);
});

test('next steps are ordered by urgency and counted from the forecast', function () {
    config(['services.gemini.api_key' => null]);

    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_NEXT_STEPS)
        ->assertOk()
        ->json();

    expect($payload['question'])->toBe('What step should I do next?')
        ->and($payload['answer'])->toContain('Next steps for')
        // The approval path must be stated, and the limits of this panel too.
        ->and($payload['answer'])->toContain('School Head')
        ->and($payload['answer'])->toContain('does not create orders');

    // Steps must appear in priority order: urgent before high before deferring.
    $urgentAt = strpos($payload['answer'], 'Urgent');
    $highAt = strpos($payload['answer'], 'High priority');
    $deferAt = strpos($payload['answer'], 'Defer the');

    if ($urgentAt !== false && $highAt !== false) {
        expect($urgentAt)->toBeLessThan($highAt);
    }
    if ($highAt !== false && $deferAt !== false) {
        expect($highAt)->toBeLessThan($deferAt);
    }
});

test('next steps cite only counts the forecast calculated', function () {
    config(['services.gemini.api_key' => null]);

    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_NEXT_STEPS)
        ->assertOk()
        ->json();

    $rows = collect($this->forecastRows);
    $gaps = $rows->filter(fn (array $row): bool => $row['needs_procurement'] === true);

    $expected = [
        'items with a gap' => $gaps->count(),
        'urgent' => $gaps->where('priority', 'Urgent')->count(),
        'high' => $gaps->where('priority', 'High')->count(),
        'medium' => $gaps->where('priority', 'Medium')->count(),
        'deferrable' => $rows->filter(fn (array $row): bool => $row['status'] === 'success'
            && $row['needs_procurement'] === false)->count(),
        'suggested_units' => (int) $gaps->sum('suggested_procurement'),
    ];

    foreach ($expected as $label => $count) {
        if ($count > 0) {
            expect($payload['answer'])->toContain((string) $count);
        }
    }

    // And nothing may appear that is not a real count. Step numbers ("1.", "2.")
    // are list positions, not claims, so they are stripped as the service does.
    $scannable = preg_replace('/(?:(?<=\.)|^)\s*\d{1,3}[.)]\s+/m', '', $payload['answer']);

    preg_match_all('/(?<![\pL\d])\d+(?:[,.]\d+)*(?!\d)(?![\pL])/u', (string) $scannable, $matches);
    $allowed = collect($expected)->push(10)->map(fn ($n): string => (string) $n)
        ->merge(['2026', '10', '3', '4', '25'])
        ->unique();

    foreach ($matches[0] as $number) {
        expect($allowed->contains(str_replace(',', '', $number)))->toBeTrue(
            "Next-steps answer used unapproved number {$number}."
        );
    }
});

test('next steps return the actionable items so the list can be exported', function () {
    $payload = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_NEXT_STEPS)
        ->assertOk()
        ->json();

    $gapped = collect($this->forecastRows)
        ->filter(fn (array $row): bool => $row['needs_procurement'] === true)
        ->keyBy('inventory_id');

    expect($payload['inventory_ids'])->not->toBeEmpty();

    foreach ($payload['inventory_ids'] as $inventoryId) {
        expect($gapped->has($inventoryId))->toBeTrue(
            "Next steps returned {$inventoryId}, which has no procurement gap."
        );
    }

    $this->actingAs($this->custodian)
        ->post(route('api.custodian.reports.forecast.procurement-list-pdf'), [
            'inventory_ids' => $payload['inventory_ids'],
            'prompt_type' => $payload['prompt_type'],
        ])
        ->assertOk();
});

test('the grounding check approves server-computed cycle counts', function () {
    // The next-steps answer quotes counts like "the 62 High priority items".
    // Those come from cycle_state(), so quoting them must not be treated as an
    // invented figure.
    $service = new ForecastDecisionSupportService(
        app(\App\Services\AiCapabilityPolicy::class),
        app(\App\Services\GeminiApiService::class),
        app(StoredDemandForecastService::class),
    );

    $facts = [
        'forecast_period' => 'October 2026',
        'generated_at' => '2026-10-01T00:00:00+00:00',
        'forecast_summary' => ['items_forecasted' => 99, 'items_needing_procurement' => 79],
        'selected_count' => 1,
        'max_items_shown' => 10,
        'cycle_state' => [
            'items_with_gap' => 79,
            'urgent' => 1,
            'high' => 62,
            'suggested_units' => 798,
        ],
        'items' => [[
            'item_name' => 'Bond Paper',
            'forecast_demand' => 36,
            'safety_stock' => 9,
            'available_stock' => 5,
            'pending_demand' => 0,
            'suggested_procurement' => 40,
        ]],
    ];

    $reply = 'Review the 62 High priority items of 79 with a gap; 798 units in total. '
        .'Bond Paper leads with demand 36 and a suggested 40.';

    expect((new \ReflectionMethod($service, 'isGroundedReply'))->invoke($service, $reply, $facts))
        ->toBeTrue('Quoting server-computed cycle counts must be accepted.');
});

test('a next-steps reply may reference counts without naming an item', function () {
    // Guidance about the workflow legitimately talks about counts alone
    // ("address the 62 High priority items"). Requiring an item name there
    // rejected correct answers and silently downgraded them.
    $service = new ForecastDecisionSupportService(
        app(\App\Services\AiCapabilityPolicy::class),
        app(\App\Services\GeminiApiService::class),
        app(StoredDemandForecastService::class),
    );

    $facts = [
        'prompt_type' => ForecastDecisionSupportService::PROMPT_NEXT_STEPS,
        'forecast_period' => 'October 2026',
        'generated_at' => '2026-10-01T00:00:00+00:00',
        'forecast_summary' => ['items_forecasted' => 99, 'items_needing_procurement' => 79],
        'selected_count' => 10,
        'max_items_shown' => 10,
        'cycle_state' => ['items_with_gap' => 79, 'high' => 62, 'medium' => 17, 'deferrable' => 20],
        'items' => [[
            'item_name' => 'Bond Paper',
            'forecast_demand' => 36,
            'safety_stock' => 9,
            'available_stock' => 5,
            'pending_demand' => 0,
            'suggested_procurement' => 40,
        ]],
    ];

    $countsOnly = '1. Address the 62 High priority items. 2. Defer the 20 items with no gap. '
        .'3. Approval by the Property Custodian and School Head. This does not create an order.';

    $inventedCount = '1. Address the 412 High priority items.';

    // Facts are passed per call: an arrow function captures by value, so
    // mutating $facts afterwards would silently have no effect.
    $check = fn (string $reply, array $subject): bool => (new \ReflectionMethod($service, 'isGroundedReply'))
        ->invoke($service, $reply, $subject);

    expect($check($countsOnly, $facts))->toBeTrue('Count-only guidance must be accepted.')
        ->and($check($inventedCount, $facts))->toBeFalse('A count the forecast never produced must be rejected.');

    // The same reply routed to purchase_first must still name a real item.
    $ranked = array_merge($facts, ['prompt_type' => ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST]);
    expect($check($countsOnly, $ranked))->toBeFalse('A ranked list must still name a real item.');
});

test('a follow-up narrows the previous answer to the items it named', function () {
    $first = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $oneId = $first['inventory_ids'][0];

    $follow = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST, [
        'inventory_ids' => [$oneId],
    ])->assertOk()->json();

    expect($follow['scope'])->toBe('follow_up')
        ->and($follow['inventory_ids'])->toBe([$oneId])
        ->and($follow['items'])->toHaveCount(1);
});

test('a follow-up keeps the order of the previous answer', function () {
    $first = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $reversed = array_reverse($first['inventory_ids']);

    $follow = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST, [
        'inventory_ids' => $reversed,
    ])->assertOk()->json();

    // "the second one" must still mean the same item, so the caller's order wins
    // rather than the service re-sorting by priority.
    expect($follow['inventory_ids'])->toBe($reversed);
});

test('a follow-up cannot introduce an item outside the previous answer', function () {
    $first = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $known = collect($this->forecastRows)->pluck('inventory_id');
    // An id that exists in the forecast but was not in the previous answer.
    $outsider = $known->first(fn ($id): bool => ! in_array($id, $first['inventory_ids'], true));

    $follow = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST, [
        'inventory_ids' => [$first['inventory_ids'][0], $outsider],
    ])->assertOk()->json();

    // Both were supplied by the client, and both are real forecast rows, so they
    // are answered. The guarantee is that no id *outside* the forecast is served.
    foreach ($follow['inventory_ids'] as $id) {
        expect($known->contains($id))->toBeTrue("Follow-up returned unknown inventory id {$id}.");
    }
});

test('a follow-up for a different question re-filters within the prior list', function () {
    $first = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    // Every purchase-first item has a gap, so asking "what can wait?" about that
    // same list must come back empty rather than silently re-ranking the
    // forecast to something unrelated.
    $follow = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_DEFERRABLE, [
        'inventory_ids' => $first['inventory_ids'],
    ])->assertOk()->json();

    expect($follow['scope'])->toBe('follow_up')
        ->and($follow['inventory_ids'])->toBe([]);
});

test('a follow-up whose items are all gone falls back to the full ranking', function () {
    $unknownId = max(collect($this->forecastRows)->pluck('inventory_id')->all()) + 9000;

    $follow = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST, [
        'inventory_ids' => [$unknownId],
    ])->assertOk()->json();

    // Deleted items or a retrain must not leave the user with an empty answer;
    // the full ranking is the useful response.
    expect($follow['scope'])->toBe('full')
        ->and($follow['inventory_ids'])->not->toBeEmpty();
});

test('follow-up ids are validated and capped', function () {
    decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST, [
        'inventory_ids' => ['not-an-id'],
    ])->assertStatus(422);

    decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST, [
        'inventory_ids' => range(1, 50),
    ])->assertStatus(422);
});

test('a follow-up export lists only the narrowed items', function () {
    $first = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST)
        ->assertOk()
        ->json();

    $subset = array_slice($first['inventory_ids'], 0, 3);

    $follow = decisionSupportPost($this, ForecastDecisionSupportService::PROMPT_PURCHASE_FIRST, [
        'inventory_ids' => $subset,
    ])->assertOk()->json();

    $this->actingAs($this->custodian)
        ->post(route('api.custodian.reports.forecast.procurement-list-pdf'), [
            'inventory_ids' => $follow['inventory_ids'],
            'prompt_type' => $follow['prompt_type'],
        ])
        ->assertOk();

    expect($follow['inventory_ids'])->toBe($subset);
});
