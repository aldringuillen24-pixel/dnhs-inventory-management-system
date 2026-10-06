<?php

namespace App\Services\Tools;

use App\Models\User;

/**
 * A ToolResult is the plain array shape the assistant has always returned:
 *
 *   ['status' => ..., 'intent' => ..., 'capability' => ..., 'answer' => [...], 'explanation_data' => [...]]
 *
 * It stays an array rather than becoming a value object so that every caller,
 * formatter and test that reads `answer()` / `explanation_data` keeps working
 * untouched. No field has been added, removed or renamed.
 */
interface ToolContract
{
    /**
     * Whether this tool serves the given capability.
     *
     * A tool claims capabilities by identity only. Role access is never decided
     * here — that is AiCapabilityPolicy's job, enforced in ToolRouter::dispatch().
     */
    public function handles(string $capability): bool;

    /**
     * Retrieve the facts for an already-authorised request.
     *
     * Implementations must not re-check permissions and must not decide that a
     * question is unsupported; ToolRouter has already done both.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>  The ToolResult shape.
     */
    public function fetch(User $user, array $request): array;
}