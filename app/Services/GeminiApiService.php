<?php

namespace App\Services;

use App\Models\AiApiKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GeminiApiService
{
    /**
     * Retryable failures: the next pooled key may succeed. Anything else
     * (notably 400) stops rotation so bad requests don't burn the pool.
     */
    private const RETRYABLE_STATUSES = [429, 500, 503];

    public function generate(string $systemInstruction, string $input, array $generationConfig = []): ?string
    {
        $model = config('services.gemini.model');
        if (! is_string($model) || trim($model) === '') {
            return null;
        }

        $pool = $this->activePool();

        foreach ($pool as $keyRecord) {
            try {
                $plaintext = $keyRecord->decryptedKey();
            } catch (\Throwable $exception) {
                Log::warning('Gemini API key could not be decrypted.', [
                    'key_id' => $keyRecord->id,
                ]);
                $keyRecord->forceFill(['last_error' => 'Key could not be decrypted.'])->save();

                continue;
            }

            $attempt = $this->requestOnce($plaintext, $model, $systemInstruction, $input, $generationConfig, $keyRecord->id);

            if ($attempt['text'] !== null) {
                $keyRecord->forceFill(['last_used_at' => now(), 'last_error' => null])->save();

                return $attempt['text'];
            }

            $keyRecord->forceFill(['last_error' => $attempt['error']])->save();

            if (! $attempt['retryable']) {
                return null;
            }
        }

        // Pool configured but every key failed retryably: fall through to
        // the local fallback (null), never to the env seed.
        if (count($pool) > 0) {
            return null;
        }

        // Zero keys: today's behavior — single GEMINI_API_KEY env fallback.
        $apiKey = config('services.gemini.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        return $this->requestOnce($apiKey, $model, $systemInstruction, $input, $generationConfig, null)['text'];
    }

    /**
     * 8-token connectivity probe against one pooled key (per-key ping).
     */
    public function probe(AiApiKey $keyRecord): bool
    {
        $model = config('services.gemini.model');
        if (! is_string($model) || trim($model) === '') {
            return false;
        }

        try {
            $plaintext = $keyRecord->decryptedKey();
        } catch (\Throwable) {
            $keyRecord->forceFill(['last_error' => 'Key could not be decrypted.'])->save();

            return false;
        }

        $attempt = $this->requestOnce(
            $plaintext,
            $model,
            'Return only the requested short text.',
            'Reply with exactly: ok',
            ['maxOutputTokens' => 8],
            $keyRecord->id
        );

        $keyRecord->forceFill([
            'last_used_at' => $attempt['text'] !== null ? now() : $keyRecord->last_used_at,
            'last_error' => $attempt['text'] !== null ? null : $attempt['error'],
        ])->save();

        return $attempt['text'] !== null;
    }

    /**
     * Active pooled keys ordered by priority. Empty when the table has not
     * been migrated yet, so old installs keep working on the env key.
     *
     * @return AiApiKey[]
     */
    private function activePool(): array
    {
        try {
            if (! Schema::hasTable('ai_api_keys')) {
                return [];
            }

            return AiApiKey::query()
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('id')
                ->get()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * One request with one key. Logs status + key_id, never the key value.
     *
     * @return array{text: ?string, retryable: bool, error: ?string, status: ?int}
     */
    private function requestOnce(
        string $apiKey,
        string $model,
        string $systemInstruction,
        string $input,
        array $generationConfig,
        ?int $keyId
    ): array {
        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->connectTimeout((int) config('services.gemini.connect_timeout', 5))
                ->timeout((int) config('services.gemini.timeout', 12))
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent', [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemInstruction]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $input]],
                    ]],
                    // Must be a JSON object, never an array. An empty PHP array
                    // encodes as `[]`, and Gemini rejects that outright:
                    // "Unknown name generationConfig: Proto field is not
                    // repeating, cannot start list." (HTTP 400). Callers that
                    // pass no options would otherwise silently get no text.
                    'generationConfig' => $generationConfig === [] ? new \stdClass : $generationConfig,
                ]);

            if (! $response->successful()) {
                $status = $response->status();
                Log::warning('Gemini API returned an unsuccessful response.', [
                    'status' => $status,
                    'key_id' => $keyId,
                    'model' => $model,
                ]);

                $retryable = in_array($status, self::RETRYABLE_STATUSES, true);

                return [
                    'text' => null,
                    'retryable' => $retryable,
                    'error' => "HTTP {$status}",
                    'status' => $status,
                ];
            }

            $parts = $response->json('candidates.0.content.parts', []);
            // Reasoning models (Gemma 4 and others) return their scratchpad as a
            // part flagged "thought": true alongside the real answer. Plucking
            // every 'text' field would splice that scratchpad into the reply the
            // user reads, and it can exhaust maxOutputTokens before the model
            // writes an answer at all — which looks like an empty response. Only
            // answer parts are collected.
            $text = collect(is_array($parts) ? $parts : [])
                ->reject(fn (mixed $part): bool => is_array($part) && ($part['thought'] ?? false) === true)
                ->pluck('text')
                ->filter(fn ($part): bool => is_string($part))
                ->implode('');

            if (trim($text) === '') {
                Log::warning('Gemini API returned no text candidate.', [
                    'finish_reason' => $response->json('candidates.0.finishReason'),
                    'block_reason' => $response->json('promptFeedback.blockReason'),
                    'key_id' => $keyId,
                    'model' => $model,
                ]);

                return ['text' => null, 'retryable' => true, 'error' => 'Empty response', 'status' => 200];
            }

            return ['text' => $text, 'retryable' => false, 'error' => null, 'status' => 200];
        } catch (ConnectionException $exception) {
            Log::warning('Gemini API request timed out.', [
                'key_id' => $keyId,
                'model' => $model,
                'exception' => $exception->getMessage(),
            ]);

            return ['text' => null, 'retryable' => true, 'error' => 'Timeout', 'status' => null];
        } catch (\Throwable $exception) {
            Log::warning('Gemini API request failed.', [
                'key_id' => $keyId,
                'model' => $model,
                'exception' => $exception->getMessage(),
            ]);

            return ['text' => null, 'retryable' => true, 'error' => 'Request failed', 'status' => null];
        }
    }
}
