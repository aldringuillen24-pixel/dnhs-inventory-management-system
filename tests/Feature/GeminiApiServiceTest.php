<?php

use App\Services\GeminiApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** Minimal well-formed Gemini success payload. */
function geminiOk(string $text = 'ok'): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
}

/**
 * These assert the request SHAPE only, using Http::fake(). They never reach
 * the network and cost no provider quota.
 */
it('sends generationConfig as a JSON object even when the caller passes no options', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    $body = null;
    Http::fake(function ($request) use (&$body) {
        $body = $request->body();

        return Http::response(geminiOk());
    });

    $result = app(GeminiApiService::class)->generate('system', 'input');

    expect($result)->toBe('ok');

    // An empty PHP array must still be sent as {}, never as [].
    expect($body)->toContain('"generationConfig":{}')
        ->and($body)->not->toContain('"generationConfig":[]');
});

it('sends generationConfig as an object when options are supplied', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);

    $body = null;
    Http::fake(function ($request) use (&$body) {
        $body = $request->body();

        return Http::response(geminiOk());
    });

    app(GeminiApiService::class)->generate('system', 'input', ['temperature' => 0.2, 'maxOutputTokens' => 500]);

    $decoded = json_decode($body, true);
    expect($decoded['generationConfig'])->toBe(['temperature' => 0.2, 'maxOutputTokens' => 500]);
});

it('returns null and logs when the provider rejects the request', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['*' => Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT']], 400)]);

    expect(app(GeminiApiService::class)->generate('system', 'input'))->toBeNull();
});

it('returns null when the provider returns no text candidate', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => []], 'finishReason' => 'SAFETY']]])]);

    expect(app(GeminiApiService::class)->generate('system', 'input'))->toBeNull();
});

it('returns null when the provider throws', function () {
    config()->set(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'test/model']);
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Timed out'));

    expect(app(GeminiApiService::class)->generate('system', 'input'))->toBeNull();
});

it('makes no request at all when no key is configured', function () {
    config()->set('services.gemini.api_key', null);
    Http::fake();

    expect(app(GeminiApiService::class)->generate('system', 'input'))->toBeNull();
    Http::assertNothingSent();
});