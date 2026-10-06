<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function geminiGenerateContentResponse(string $text): array
{
    return [
        'candidates' => [[
            'content' => [
                'role' => 'model',
                'parts' => [['text' => $text]],
            ],
            'finishReason' => 'STOP',
        ]],
    ];
}

/**
 * Whether a live-provider test must be skipped, and why.
 *
 * Tests that reach Gemini for real spend the project's request quota, which is
 * a shared, finite resource. A full suite run must therefore never call the
 * provider by accident: `.env` supplies a real GEMINI_API_KEY, so key
 * presence alone is not a safe signal that someone meant to run these.
 *
 * Return null to proceed, or a reason string to skip with.
 */
function liveGeminiSkipReason(): ?string
{
    $flag = getenv('GEMINI_LIVE_CHECK');
    if ($flag === false || $flag === '') {
        $flag = $_ENV['GEMINI_LIVE_CHECK'] ?? $_SERVER['GEMINI_LIVE_CHECK'] ?? '';
    }

    if ((string) $flag !== '1') {
        return 'Live Gemini checks are opt-in. Re-run with GEMINI_LIVE_CHECK=1 to spend provider quota.';
    }

    if (! is_string(config('services.gemini.api_key')) || trim((string) config('services.gemini.api_key')) === '') {
        return 'No Gemini key configured.';
    }

    return null;
}
