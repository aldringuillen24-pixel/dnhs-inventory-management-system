<?php

use App\Models\AiApiKey;
use App\Models\Role;
use App\Models\User;
use App\Services\GeminiApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::firstOrCreate(['role_name' => 'Administrator']);

    $this->admin = User::create([
        'role_id' => $this->adminRole->role_id,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'username' => 'syskeyadmin',
        'email' => 'syskeyadmin@example.com',
        'password' => 'password',
        'status' => 'active',
    ]);

    config()->set('services.gemini.api_key', null);
});

function storeSystemKey($test, string $label, string $provider = 'gemini', int $priority = 0)
{
    return $test->actingAs($test->admin)->postJson(route('api.admin.settings.keys.store'), [
        'provider' => $provider,
        'label' => $label,
        'key' => str_repeat('k', 20).$label,
        'priority' => $priority,
    ]);
}

test('admin can store provider keys and the list stays masked', function () {
    storeSystemKey($this, 'gem-1')->assertCreated();
    storeSystemKey($this, 'fc-1', 'forecast')->assertCreated();
    storeSystemKey($this, 'legacy')->assertCreated(); // provider defaults to gemini

    $response = $this->actingAs($this->admin)->getJson(route('api.admin.settings.keys.index'))->assertOk();
    $keys = $response->json('keys');

    expect($keys)->toHaveCount(3)
        ->and(collect($keys)->pluck('provider')->all())->toEqual(['forecast', 'gemini', 'gemini'])
        ->and(collect($keys)->pluck('masked_key')->all())->each->toStartWith('…')
        ->and(json_encode($keys))->not->toContain(str_repeat('k', 20));
});

test('the five-active-key cap applies per provider', function () {
    foreach (range(1, 5) as $i) {
        storeSystemKey($this, "gem-{$i}")->assertCreated();
    }

    storeSystemKey($this, 'gem-6')->assertStatus(422);

    // A full Gemini pool never blocks other providers (and vice versa).
    storeSystemKey($this, 'fc-1', 'forecast')->assertCreated();
});

test('admin can disable, re-enable within capacity, and delete keys', function () {
    $id = storeSystemKey($this, 'gem-1')->json('key.id');

    $this->actingAs($this->admin)
        ->patchJson(route('api.admin.settings.keys.update', $id), ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('key.is_active', false);

    $this->actingAs($this->admin)
        ->patchJson(route('api.admin.settings.keys.update', $id), ['is_active' => true])
        ->assertOk()
        ->assertJsonPath('key.is_active', true);

    $this->actingAs($this->admin)
        ->deleteJson(route('api.admin.settings.keys.destroy', $id))
        ->assertOk()
        ->assertJson(['deleted' => true]);

    expect(AiApiKey::find($id))->toBeNull();
});

test('gemini ping probes live while forecast ping reports stored-only', function () {
    $gemId = storeSystemKey($this, 'gem-1')->json('key.id');
    $fcId = storeSystemKey($this, 'fc-1', 'forecast')->json('key.id');

    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]])]);

    $this->actingAs($this->admin)->postJson(route('api.admin.settings.keys.ping', $gemId))
        ->assertOk()
        ->assertJson(['ok' => true]);

    Http::assertSentCount(1);

    $this->actingAs($this->admin)->postJson(route('api.admin.settings.keys.ping', $fcId))
        ->assertOk()
        ->assertJson(['ok' => false])
        ->assertJsonPath('key.provider', 'forecast');

    // The forecast probe never touches the network.
    Http::assertSentCount(1);
});

test('gemini failover rotates past quota errors and ignores forecast rows', function () {
    storeSystemKey($this, 'gem-first', 'gemini', 0);
    storeSystemKey($this, 'gem-second', 'gemini', 1);
    $forecastId = storeSystemKey($this, 'fc-1', 'forecast')->json('key.id');

    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            return Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429);
        }

        return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'second']]]]]]);
    });

    expect(app(GeminiApiService::class)->generate('system', 'input'))->toBe('second')
        ->and($calls)->toBe(2)
        ->and(AiApiKey::where('label', 'gem-first')->first()->last_error)->toBe('HTTP 429')
        ->and(AiApiKey::where('label', 'gem-second')->first()->last_used_at)->not->toBeNull()
        // The forecast row is never attempted: no error, no usage stamp.
        ->and(AiApiKey::find($forecastId)->last_error)->toBeNull()
        ->and(AiApiKey::find($forecastId)->last_used_at)->toBeNull();
});

test('a forecast-only pool sends no gemini traffic and reports no gemini key', function () {
    storeSystemKey($this, 'fc-1', 'forecast')->assertCreated();

    Http::fake();
    expect(app(GeminiApiService::class)->generate('system', 'input'))->toBeNull();
    Http::assertNothingSent();

    $this->actingAs($this->admin)->getJson(route('api.admin.settings'))
        ->assertOk()
        ->assertJsonPath('systemDiagnostics.geminiKeySet', false);
});

function geminiProbeOk(array $extra = []): array
{
    return array_merge(
        ['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]],
        $extra,
    );
}

test('ping stores token usage, ok flag, and check time', function () {
    $id = storeSystemKey($this, 'gem-1')->json('key.id');

    Http::fake(['*' => Http::response(geminiProbeOk([
        'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 2, 'totalTokenCount' => 7],
    ]))]);

    $this->actingAs($this->admin)->postJson(route('api.admin.settings.keys.ping', $id))
        ->assertOk()
        ->assertJson(['ok' => true])
        ->assertJsonPath('key.last_tokens_sent', 7)
        ->assertJsonPath('key.last_ping_ok', true);

    $key = AiApiKey::find($id);
    expect($key->last_tokens_sent)->toBe(7)
        ->and($key->last_checked_at)->not->toBeNull();

    // The pool list mirrors the same fields for the status card.
    $this->actingAs($this->admin)->getJson(route('api.admin.settings.keys.index'))
        ->assertOk()
        ->assertJsonPath('keys.0.last_tokens_sent', 7)
        ->assertJsonPath('keys.0.last_ping_ok', true);
});

test('ping without provider usage stores null tokens but still records the check', function () {
    $id = storeSystemKey($this, 'gem-1')->json('key.id');

    Http::fake(['*' => Http::response(geminiProbeOk())]);

    $this->actingAs($this->admin)->postJson(route('api.admin.settings.keys.ping', $id))
        ->assertOk()
        ->assertJson(['ok' => true])
        ->assertJsonPath('key.last_tokens_sent', null)
        ->assertJsonPath('key.last_ping_ok', true);

    expect(AiApiKey::find($id)->last_checked_at)->not->toBeNull();
});

test('failed ping records fail and time while keeping prior tokens', function () {    $id = storeSystemKey($this, 'gem-1')->json('key.id');
    AiApiKey::find($id)->forceFill(['last_tokens_sent' => 9])->save();

    Http::fake(['*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT']], 400)]);

    $this->actingAs($this->admin)->postJson(route('api.admin.settings.keys.ping', $id))
        ->assertOk()
        ->assertJson(['ok' => false])
        ->assertJsonPath('key.last_ping_ok', false)
        ->assertJsonPath('key.last_tokens_sent', 9);

    $key = AiApiKey::find($id);
    expect($key->last_checked_at)->not->toBeNull()
        ->and($key->last_used_at)->toBeNull();
});

test('env fallback key appears as its own card state', function () {
    config()->set('services.gemini.api_key', 'test-env-key-1234567890');

    // Empty pool: the env key serves traffic.
    $this->actingAs($this->admin)->getJson(route('api.admin.settings'))
        ->assertOk()
        ->assertJsonPath('envKey.masked_key', '…7890')
        ->assertJsonPath('envKey.in_use', true);

    // Once a pooled key exists the pool takes over.
    storeSystemKey($this, 'gem-1')->assertCreated();

    $this->actingAs($this->admin)->getJson(route('api.admin.settings.keys.index'))
        ->assertOk()
        ->assertJsonPath('envKey.masked_key', '…7890')
        ->assertJsonPath('envKey.in_use', false);
});

test('no env card state without an env key', function () {
    $this->actingAs($this->admin)->getJson(route('api.admin.settings'))
        ->assertOk()
        ->assertJson(['envKey' => null]);
});
