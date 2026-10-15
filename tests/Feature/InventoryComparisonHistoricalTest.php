<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\AiApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 12:00:00'));
    config(['services.gemini.api_key' => null]);

    $role = Role::firstOrCreate(['role_name' => 'Property Custodian']);
    $this->custodian = User::factory()->create(['role_id' => $role->role_id]);
});

test('historical comparison reads item usage from the bundled CSV', function () {
    $response = $this->actingAs($this->custodian)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'source' => 'historical',
        'metric' => 'monthly_usage',
        'scope' => 'item',
        'item_id' => 9001,
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    expect($response->json('result.source'))->toBe('historical')
        ->and($response->json('result.data_notice'))->toContain('historical usage data')
        ->and($response->json('result.scope_label'))->toBe('Bond Paper (A4)')
        ->and($response->json('result.series.0.period_one'))->toBe(120)
        ->and($response->json('result.series.0.period_two'))->toBe(95)
        ->and($response->json('result.series.0.difference'))->toBe(-25)
        ->and($response->json('result.explanation_source'))->toBe('local')
        ->and($response->json('result.explanation'))->toContain('recorded monthly usage was 120 reams')
        ->and($response->json('result.explanation'))->toContain('decreased by 25 reams');
});

test('historical comparison uses AI to provide a grounded longer explanation', function () {
    config(['services.gemini.api_key' => 'test-key']);
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [[
            'text' => 'Bond Paper (A4) recorded monthly usage of 120 reams in June 2026 and 95 reams in July 2026. '
                .'Usage decreased by 25 reams, a 20.83% decrease from June to July. '
                .'This indicates lower recorded usage in July than June; the comparison reports the change but cannot determine its cause. '
                .'These figures describe historical usage, not current stock or a forecast.',
        ]]]]],
    ])]);

    $response = $this->actingAs($this->custodian)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'source' => 'historical',
        'metric' => 'monthly_usage',
        'scope' => 'item',
        'item_id' => 9001,
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    expect($response->json('result.explanation_source'))->toBe('ai')
        ->and($response->json('result.explanation'))->toContain('20.83%', 'cannot determine its cause')
        ->and(mb_strlen($response->json('result.explanation')))->toBeGreaterThan(200);
});

test('historical comparison uses an active database AI key when no environment key is configured', function () {
    config([
        'services.gemini.api_key' => null,
        'services.gemini.model' => 'test/model',
    ]);
    AiApiKey::create([
        'label' => 'Historical comparison',
        'key_encrypted' => AiApiKey::encryptKey('test-gemini-key-material-for-history'),
        'is_active' => true,
        'priority' => 1,
    ]);

    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [[
            'text' => 'Bond Paper (A4) recorded monthly usage was 120 reams in June 2026 and 95 reams in July 2026. '
                .'Usage decreased by 25 reams, a 20.8% decrease. The recorded usage was lower in July than in June. '
                .'This is historical usage only and does not indicate current stock or forecast future demand.',
        ]]]]],
    ])]);

    $response = $this->actingAs($this->custodian)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'source' => 'historical',
        'metric' => 'monthly_usage',
        'scope' => 'item',
        'item_id' => 9001,
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'generateContent'));
    expect($response->json('result.explanation_source'))->toBe('ai')
        ->and($response->json('result.explanation'))->toContain('20.8%');
});

test('historical comparison rejects AI explanations that introduce unsupported numbers', function () {
    config(['services.gemini.api_key' => 'test-key']);
    Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [[
            'text' => 'Bond Paper (A4) recorded monthly usage of 120 reams in June 2026 and 95 reams in July 2026. '
                .'Usage decreased by 25 reams, a 20.83% decrease. The usage was 50% lower after the comparison. '
                .'These figures describe historical usage, not current stock or a forecast.',
        ]]]]],
    ])]);

    $response = $this->actingAs($this->custodian)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'source' => 'historical',
        'metric' => 'monthly_usage',
        'scope' => 'item',
        'item_id' => 9001,
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    expect($response->json('result.explanation_source'))->toBe('local')
        ->and($response->json('result.explanation'))->not->toContain('50%');
});

test('historical comparison aggregates one selected unit without mixing units', function () {
    $response = $this->actingAs($this->custodian)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'source' => 'historical',
        'metric' => 'monthly_usage',
        'scope' => 'all',
        'unit' => 'reams',
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    expect($response->json('result.series'))->toHaveCount(1)
        ->and($response->json('result.series.0.unit'))->toBe('reams')
        ->and($response->json('result.series.0.period_one'))->toBe(190)
        ->and($response->json('result.series.0.period_two'))->toBe(147)
        ->and($response->json('result.source'))->toBe('historical');
});

test('historical comparison rejects unsupported metrics and months outside the CSV history window', function () {
    $this->actingAs($this->custodian)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'source' => 'historical',
        'metric' => 'stock_out_quantity',
        'scope' => 'all',
        'unit' => 'reams',
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertUnprocessable()->assertJsonValidationErrors('metric');

    $this->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'source' => 'historical',
        'metric' => 'monthly_usage',
        'scope' => 'all',
        'unit' => 'reams',
        'month_one' => '2026-05',
        'month_two' => '2026-07',
    ])->assertUnprocessable()->assertJsonValidationErrors('month_one');
});

test('historical usage questions default to CSV records and expose their month/item choices', function () {
    $response = $this->actingAs($this->custodian)->postJson(route('ai.chat'), [
        'message' => 'Compare monthly usage between June 2026 and September 2026',
    ])->assertOk();

    expect($response->json('comparison_form.source'))->toBe('historical')
        ->and($response->json('comparison_form.historical_metrics.0.value'))->toBe('monthly_usage')
        ->and($response->json('comparison_form.historical_months'))->toBe(['2026-06', '2026-07', '2026-08', '2026-09'])
        ->and($response->json('comparison_form.month_one'))->toBe('2026-06')
        ->and($response->json('comparison_form.month_two'))->toBe('2026-09')
        ->and(collect($response->json('comparison_form.historical_items'))->firstWhere('value', 9001)['label'])
        ->toContain('Bond Paper (A4)');
});

test('omitting the source keeps the existing live comparison behavior', function () {
    $response = $this->actingAs($this->custodian)->postJson(route('ai.comparison'), [
        'confirmed' => true,
        'metric' => 'request_count',
        'scope' => 'all',
        'month_one' => '2026-06',
        'month_two' => '2026-07',
    ])->assertOk();

    expect($response->json('result.source'))->toBe('live')
        ->and($response->json('result.data_notice'))->toBeNull();
});
