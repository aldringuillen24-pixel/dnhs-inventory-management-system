<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiApiService
{
    public function generate(string $systemInstruction, string $input, array $generationConfig = []): ?string
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');
        if (! is_string($apiKey) || trim($apiKey) === '' || ! is_string($model) || trim($model) === '') {
            return null;
        }

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
                Log::warning('Gemini API returned an unsuccessful response.', [
                    'status' => $response->status(),
                    'model' => $model,
                ]);

                return null;
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
                    'model' => $model,
                ]);

                return null;
            }

            return $text;
        } catch (\Throwable $exception) {
            Log::warning('Gemini API request failed.', [
                'model' => $model,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}