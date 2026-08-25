<?php

namespace App\Http\Controllers;

use App\Services\AiInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiAssistantController extends Controller
{
    public function __construct(
        protected AiInventoryService $aiService
    ) {}

    /**
     * Handle incoming chat message from the AI Assistant Drawer.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array'],
            'history.*.sender' => ['required_with:history', 'string', 'in:user,ai'],
            'history.*.text'   => ['required_with:history', 'string'],
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        try {
            $reply = $this->aiService->ask(
                $user,
                $validated['message'],
                $validated['history'] ?? []
            );

            return response()->json([
                'success' => true,
                'reply'   => $reply,
                'role'    => $user->role?->role_name ?? 'End User',
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Assistant Controller Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Unable to process your question at this moment. Please try again.',
            ], 500);
        }
    }
}
