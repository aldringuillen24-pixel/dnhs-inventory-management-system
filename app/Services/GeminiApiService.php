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
                    'generationConfig' => $generationConfig,
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini API returned an unsuccessful response.', [
                    'status' => $response->status(),
                    'model' => $model,
                ]);

                return null;
            }

            $parts = $response->json('candidates.0.content.parts', []);
            $text = collect(is_array($parts) ? $parts : [])
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