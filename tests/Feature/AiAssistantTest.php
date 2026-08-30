<?php

use App\Models\User;
use App\Services\AiInventoryService;
use Illuminate\Support\Facades\Http;

function isolatedAiService(array $context = []): AiInventoryService
{
    return new class($context) extends AiInventoryService
    {
        public function __construct(private array $testContext) {}

        public function buildRoleScopedContext(User $user): array
        {
            return $this->testContext;
        }

        protected function buildSystemPrompt(User $user, string $roleName, array $contextData): string
        {
            return 'Test prompt';
        }
    };
}

test('the current question is included once in the provider payload', function () {
    $question = 'How many bond paper boxes are available?';
    $payload = [];

    config()->set([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.max_attempts' => 1,
    ]);

    Http::fake(function ($request) use (&$payload) {
        $payload = $request->data();

        return Http::response([
            'choices' => [
                ['message' => ['content' => 'There are 10 boxes available.']],
            ],
        ]);
    });

    $service = isolatedAiService();

    $reply = $service->ask(
        new User(),
        $question,
        [
            ['sender' => 'user', 'text' => 'What was asked before?'],
            ['sender' => 'user', 'text' => $question],
        ]
    );

    expect($reply)->toBe('There are 10 boxes available.')
        ->and(collect($payload['messages'])
            ->where('role', 'user')
            ->pluck('content')
            ->filter(fn (string $content): bool => $content === $question)
            ->count())->toBe(1);
});

test('successful provider responses are returned to the caller', function () {
    config()->set([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.max_attempts' => 1,
    ]);
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => 'Provider answer']]],
        ]),
    ]);

    expect(isolatedAiService()->ask(new User(), 'What is available?'))->toBe('Provider answer');
    Http::assertSentCount(1);
});

test('missing API keys use the grounded local fallback without an HTTP request', function () {
    config()->set('services.openrouter.api_key', null);

    $service = isolatedAiService([
        'assigned_items' => [],
    ]);

    expect($service->ask(new User(), 'What items are assigned to me?'))
        ->toBe('You currently have no equipment assigned in your custody.');
    Http::assertNothingSent();
});

test('provider failures fall back locally after the configured attempts', function () {
    config()->set([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.max_attempts' => 2,
    ]);
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([], 429),
    ]);

    $response = isolatedAiService([
        'low_stock_critical_items' => [
            ['item_name' => 'Bond Paper', 'category' => 'Supplies', 'available_stock' => 1, 'unit' => 'box'],
        ],
    ])->ask(new User(), 'What should we procure first?');

    expect($response)
        ->toContain('Live OpenRouter AI is temporarily unavailable')
        ->and(Http::recorded())->toHaveCount(2);
});

test('invalid model and server errors are both retried before fallback', function () {
    config()->set([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.max_attempts' => 2,
    ]);
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
            ->push(['error' => ['message' => 'Model not found']], 404)
            ->push(['error' => ['message' => 'Temporary provider failure']], 500),
    ]);

    expect(isolatedAiService(['assigned_items' => []])
        ->ask(new User(), 'What items are assigned to me?'))
        ->toContain('You currently have no equipment assigned')
        ->and(Http::recorded())->toHaveCount(2);
});

test('malformed provider payloads use the local fallback', function () {
    config()->set([
        'services.openrouter.api_key' => 'test-key',
        'services.openrouter.model' => 'test/model',
        'services.openrouter.max_attempts' => 1,
    ]);
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => []]),
    ]);

    expect(isolatedAiService(['assigned_items' => []])
        ->ask(new User(), 'What items are assigned to me?'))
        ->toContain('You currently have no equipment assigned');
});
